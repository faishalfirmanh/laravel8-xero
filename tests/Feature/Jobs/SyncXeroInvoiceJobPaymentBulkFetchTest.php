<?php

namespace Tests\Feature\Jobs;

use App\Jobs\SyncXeroInvoiceJob;
use App\Models\MasterData\BankXero;
use App\Models\SyncJobStatus;
use App\Models\Transaction\TransactionNominalBankAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

/**
 * Versi FINAL (Opsi A — getTenantId() sudah protected).
 *
 * FIX dari versi sebelumnya:
 *   - BankXero di-seed dulu sebelum job jalan, supaya 150 payment
 *     bisa benar-benar tersimpan ke DB (bukan dilewati semua karena
 *     $bankMap kosong).
 *   - AccountCode di fake payment diubah jadi 'BANK-TEST-001' yang
 *     match dengan bank yang di-seed.
 *   - assertSame(150, ...) sekarang bisa lulus karena payment memang
 *     ter-upsert ke TransactionNominalBankAccount.
 *   - Ditambah assert DB langsung: verifikasi baris payment ada di DB.
 *
 * CARA JALANKAN:
 *   php artisan test --filter=SyncXeroInvoiceJobPaymentBulkFetchTest
 */
class SyncXeroInvoiceJobPaymentBulkFetchTest extends TestCase
{
    use RefreshDatabase;

    // Account code yang dipakai di fake payment, harus match bank yang di-seed.
    private const BANK_CODE = 'BANK-TEST-001';

    protected function setUp(): void
    {
        parent::setUp();

        // ── Seed BankXero — wajib ada supaya processPaymentsBatch()
        //   tidak skip semua payment karena $bankMap kosong. ────────────────
        //
        // unguard() dipakai supaya kolom apapun bisa diisi terlepas
        // dari $fillable model BankXero.
        //
        // Kalau BankXero punya kolom NOT NULL tambahan (selain code/name),
        // tambahkan di sini. Cek: php artisan migrate:status dan lihat
        // migration create_bank_xeros_table untuk kolom wajibnya.
        BankXero::unguard();
        BankXero::create([
            'code' => self::BANK_CODE,
            'name' => 'Bank Testing BRI',
            'created_at' => now(),
            'updated_at' => now(),
            'account_id' => '73fe8a9b-8c67-454a-a68f',
            'type' => 'BANK',
            'currency_code' => 'IDR',
            'account_number' => 11223902,
            'status' => 1
        ]);
        BankXero::reguard();
    }

    public function test_payment_sync_menggunakan_bulk_fetch_bukan_per_id(): void
    {
        Cache::flush();

        $rateLimitHeaders = [
            'X-MinLimit-Remaining' => '59',
            'X-DayLimit-Remaining' => '4999',
        ];

        Http::fake([
            // ── 1 halaman invoice (1 invoice, Payments dikosongkan) ──────
            'api.xero.com/api.xro/2.0/Invoices*' => Http::response([
                'Invoices' => [
                    [
                        'InvoiceID' => 'invoice-uuid-1',
                        'InvoiceNumber' => 'INV-0001',
                        'AmountPaid' => 5000000,
                        'Total' => 5000000,
                        'AmountDue' => 0,
                        'DateString' => '2026-01-10T00:00:00',
                        'DueDateString' => '2026-01-20T00:00:00',
                        'Status' => 'PAID',
                        'Contact' => ['ContactID' => 'contact-1', 'Name' => 'Jamaah Test'],
                        'Reference' => 'REF-001',
                        'LineItems' => [],
                        // PENTING: sengaja dikosongkan — payment sekarang
                        // diambil murni lewat bulk GET /Payments, bukan dari
                        // array ini. Ini juga membuktikan FIX #3 benar-benar
                        // memisahkan dua phase.
                        'Payments' => [],
                    ],
                ],
            ], 200, $rateLimitHeaders),

            // ── Halaman 1 payment bulk (100 payment) ─────────────────────
            'api.xero.com/api.xro/2.0/Payments?*page=1*' => Http::response(
                ['Payments' => $this->makePayments(1, 100)],
                200,
                $rateLimitHeaders
            ),

            // ── Halaman 2 payment bulk (50 payment — tanda last page) ────
            'api.xero.com/api.xro/2.0/Payments?*page=2*' => Http::response(
                ['Payments' => $this->makePayments(101, 150)],
                200,
                $rateLimitHeaders
            ),

            // ── Safety net: kalau job regresi ke fetch per-ID, request
            //   jatuh ke sini dan assert jumlah request di bawah akan gagal.
            'api.xero.com/api.xro/2.0/Payments/*' => Http::response(
                ['Payments' => []],
                200,
                $rateLimitHeaders
            ),
        ]);

        $job = Mockery::mock(SyncXeroInvoiceJob::class, [
            ['access_token' => 'fake-token-testing'],
            'job-test-001',
        ])->makePartial();

        $job->shouldAllowMockingProtectedMethods();
        $job->shouldReceive('getTenantId')->andReturn('tenant-test-123');

        $job->handle();

        // ================================================================
        // ASSERT 1 — Jumlah HTTP request ke Xero (paling kritis)
        // ================================================================
        // Yang diharapkan: 1 (invoices) + 2 (payment pages) = 3 request.
        // Kalau kode lama (N+1 per-ID), harusnya 1 + 150 = 151 request.
        $recorded = Http::recorded();
        $totalRequests = count($recorded);

        $this->assertLessThanOrEqual(
            3,
            $totalRequests,
            "Terlalu banyak request ke Xero ({$totalRequests} request). " .
            "Bulk fetch seharusnya cukup 3 request total (1 invoice + 2 payment page). " .
            "Kemungkinan regresi balik ke pola N+1 lama."
        );

        // ================================================================
        // ASSERT 2 — Tidak ada request per-ID ke GET /Payments/{uuid}
        // ================================================================
        $perIdRequests = collect($recorded)
            ->filter(function ($pair) {
                // Match URL: /Payments/some-uuid (bukan /Payments?page=N)
                return preg_match(
                    '#/api\.xro/2\.0/Payments/[a-zA-Z0-9\-]+$#',
                    $pair[0]->url()
                );
            })
            ->count();

        $this->assertSame(
            0,
            $perIdRequests,
            "Job TIDAK boleh memanggil GET /Payments/{id} satu-per-satu. " .
            "Kalau ini gagal, berarti regresi ke pola N+1 lama yang bikin sering kena limit."
        );

        // ================================================================
        // ASSERT 3 — SyncJobStatus: phase, invoice count, payment progress
        // ================================================================
        $jobStatus = SyncJobStatus::where('job_id', 'job-test-001')->first();

        $this->assertNotNull($jobStatus, 'Row SyncJobStatus harus terbuat.');
        $this->assertSame('done', $jobStatus->current_phase, 'current_phase harus "done".');
        $this->assertSame(1, (int) $jobStatus->total_synced, 'Harus tersimpan 1 invoice.');
        $this->assertSame(2, (int) $jobStatus->total_pages_payment, 'Payment harus melalui 2 halaman.');
        $this->assertSame(150, (int) $jobStatus->total_payment_synced, 'Harus tersimpan 150 payment.');

        // ================================================================
        // ASSERT 4 — Data DB: verifikasi beberapa payment benar-benar ada
        // ================================================================
        $this->assertDatabaseHas('transaction_nominal_bank_accounts', [
            'payment_uuid' => 'payment-uuid-1',
        ]);

        $this->assertDatabaseHas('transaction_nominal_bank_accounts', [
            'payment_uuid' => 'payment-uuid-100',
        ]);

        $this->assertDatabaseHas('transaction_nominal_bank_accounts', [
            'payment_uuid' => 'payment-uuid-150',
        ]);

        $totalInDb = TransactionNominalBankAccount::count();
        $this->assertSame(150, $totalInDb, 'Harus ada tepat 150 baris payment di DB.');
    }

    // ================================================================
    // HELPER — buat array payment fake
    // ================================================================

    private function makePayments(int $from, int $to): array
    {
        $payments = [];
        for ($i = $from; $i <= $to; $i++) {
            $payments[] = [
                'PaymentID' => "payment-uuid-{$i}",
                'Amount' => 50000,
                'Date' => '2026-01-15T00:00:00',
                'Reference' => "PAY-{$i}",
                // Account code HARUS match dengan bank yang di-seed di setUp().
                'Account' => ['Code' => self::BANK_CODE],
                'Invoice' => ['InvoiceID' => 'invoice-uuid-1'],
                'PaymentType' => 'ACCRECPAYMENT',
            ];
        }
        return $payments;
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}