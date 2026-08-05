<?php

namespace App\Jobs;

use App\Http\Repository\MasterData\DataJamaahXeroRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Services\XeroService;
use App\Models\Transaction\VaTransUser;
use Str;
use Illuminate\Support\Facades\Hash;

class ProcessXeroInvoiceEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected array $event;

    private $repo_contact;

    public function __construct(array $event, DataJamaahXeroRepository $repo_contact)
    {
        $this->event = $event;
        $this->repo_contact = $repo_contact;
    }

    public function handle(XeroService $xero)
    {
        $invoiceId = $this->event['resourceId'] ?? null;
        $tenantId = $this->event['tenantId'] ?? null;

        if (!$invoiceId || !$tenantId) {
            return;
        }

        try {
            // Ambil detail invoice terbaru dari Xero API
            $invoice = $xero->getInvoice($tenantId, $invoiceId);

            $invoiceNumber = $invoice['InvoiceNumber'] ?? $invoiceId; // contoh: INV-1219
            $safeName = preg_replace('/[^A-Za-z0-9\-_]/', '', $invoiceNumber);
            $fileName = $safeName . '.json';

            Storage::disk('local')->put(
                'xero-invoices/' . $fileName,
                json_encode($invoice, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            );

            $this->syncVaTransFromLineItems($invoice, $invoiceNumber);

            Log::info("Invoice {$invoiceNumber} berhasil diupdate ke {$fileName}");
        } catch (\Throwable $e) {
            Log::error('Gagal proses webhook invoice Xero: ' . $e->getMessage());
            $this->fail($e);
        }
    }


    private static function ambilKataPertama(string $str): string
    {
        $bersih = rtrim($str, '_');
        $parts = explode(' ', $bersih);
        return $parts[0];
    }


    protected function syncVaTransFromLineItems(array $invoice, string $invoiceNumber): void
    {
        $lineItems = $invoice['LineItems'] ?? [];
        $contactName = $invoice['Contact']['Name'] ?? null;
        $contactPhone = $this->extractContactPhone($invoice);

        $vaNumber = null;
        $bankName = null;
        $paketName = null;
        $totPayment = 0;
        $totNominal = 0;

        $user_name_msg = '';
        $user_pass_msg = '';

        if ($contactName) {
            $cari_contact = $this->repo_contact->whereData(['uuid_contact' => $invoice['Contact']['ContactID']])->first();
            $rand_number = random_int(1000, 9999);

            $baseName = self::ambilKataPertama($contactName);
            $generate_user = $baseName . $rand_number;


            $plain_password = $baseName;
            $pass_user = Hash::make($plain_password);

            $contactnya = [];

            if ($cari_contact == NULL) {
                $contactnya['uuid_contact'] = $invoice['Contact']['ContactID'];
                $contactnya['full_name'] = trim(($invoice['Contact']['FirstName'] ?? '') . ' ' . ($invoice['Contact']['LastName'] ?? ''));
                $contactnya['phone_number'] = $invoice['Contact']['Phones'][0]['PhoneNumber'] ?? '';
                $contactnya['username'] = $generate_user;
                $contactnya['pass'] = $pass_user;
                $user_name_msg = $generate_user;
                $user_pass_msg = $plain_password;
                $this->repo_contact->CreateOrUpdate([$contactnya], null);
            } else {
                if ($cari_contact->username == null) {
                    $contactnya['username'] = $generate_user;
                    $contactnya['pass'] = $pass_user;
                    $user_name_msg = $generate_user;
                    $user_pass_msg = $plain_password;
                    $this->repo_contact->CreateOrUpdate([$contactnya], $cari_contact->id);
                }
            }
        }

        foreach ($lineItems as $item) {
            $description = $item['Description'] ?? '';
            $lineAmount = (float) ($item['LineAmount'] ?? 0);

            // 1) Deteksi baris deklarasi VA - independen dari nilai amount-nya
            $vaInfo = $this->extractVaInfo($description);
            if ($vaInfo) {
                if ($vaNumber === null) {
                    $vaNumber = $vaInfo['va_number'];
                    $bankName = $vaInfo['bank_name'];
                    $paketName = $this->extractPaketName($item) ?? '--';
                } elseif ($vaNumber !== $vaInfo['va_number']) {
                    Log::warning("Invoice {$invoiceNumber}: va_number berbeda ({$vaInfo['va_number']}) dari yang pertama ({$vaNumber}), diabaikan.");
                }
            }

            // 2) Baris amount negatif = riwayat pembayaran (TIDAK harus mengandung teks VA)
            if ($lineAmount < 0) {
                $totPayment += abs($lineAmount);
            }

            // 3) Baris amount positif = nominal paket/produk asli
            if ($lineAmount > 0) {
                $totNominal += $lineAmount;
            }
        }

        if ($vaNumber === null) {
            return; // tidak ada deklarasi VA di invoice ini
        }

        $existing = VaTransUser::where('va_number', $vaNumber)->first();

        if ($existing) {
            $paymentChanged = (float) $existing->payment !== $totPayment;
            $existing->update([
                'payment' => $totPayment,
                'total_nominal' => $totNominal,
            ]);
            Log::info("Sukses update VaTransUser untuk invoice {$invoiceNumber}");
            if ($paymentChanged) {
                $this->dispatchVaNotification($contactPhone, $invoiceNumber, $vaNumber, $bankName, $existing->paket_name, $totPayment, $totNominal, $user_name_msg, $user_pass_msg);
            }
        } else {
            VaTransUser::create([
                'inv_number' => $invoiceNumber,
                'va_number' => $vaNumber,
                'paket_name' => $paketName,
                'bank_name' => $bankName,
                'name_contact' => $contactName,
                'payment' => $totPayment,
                'total_nominal' => $totNominal,
            ]);
            Log::info("Sukses create VaTransUser untuk invoice {$invoiceNumber}");
            $this->dispatchVaNotification($contactPhone, $invoiceNumber, $vaNumber, $bankName, $paketName, $totPayment, $totNominal, $user_name_msg, $user_pass_msg);
        }
    }

    private function extractContactPhone(array $invoice): ?string
    {
        $phones = $invoice['Contact']['Phones'] ?? [];

        $mobile = collect($phones)->firstWhere('PhoneType', 'MOBILE');
        $phone = $mobile['PhoneNumber'] ?? (collect($phones)->first()['PhoneNumber'] ?? null);

        if (!$phone) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $phone);

        if (Str::startsWith($digits, '0')) {
            $digits = '62' . substr($digits, 1);
        } elseif (!Str::startsWith($digits, '62')) {
            $digits = '62' . $digits;
        }

        return $digits;
    }

    private function dispatchVaNotification(?string $phone, string $invoiceNumber, string $vaNumber, ?string $bankName, ?string $paketName, float $totPayment, float $totNominal, $user = null, $pass = null): void
    {
        if (!$phone) {
            Log::warning("Gagal kirim notifikasi VA invoice {$invoiceNumber}: nomor HP kontak tidak ditemukan di data Xero.");
            return;
        }

        // dispatch ke queue, jangan blocking di dalam proses webhook Xero
        SendVaNotificationJob::dispatch($phone, $invoiceNumber, $vaNumber, $bankName, $paketName, $totPayment, $totNominal, $user, $pass);
    }

    protected function extractVaInfo(string $description): ?array
    {
        $upper = strtoupper($description);

        // 1) Cari keyword VIRTUAL ACCOUNT / VA (word boundary), ambil sisa teks setelahnya
        if (!preg_match('/\b(?:VIRTUAL\s*ACCOUNT|VA)\b\s*[:\-]?\s*(.+)$/', $upper, $matches)) {
            return null;
        }

        $remainder = trim($matches[1]);

        // 2) Angka di ujung string = nomor VA, sisanya = nama bank
        if (!preg_match('/^([A-Z\s]+?)\s+(\d{4,})\s*$/', $remainder, $parts)) {
            return null;
        }

        return [
            'bank_name' => trim($parts[1]),
            'va_number' => trim($parts[2]),
        ];
    }

    protected function extractPaketName(array $item): ?string
    {
        foreach ($item['Tracking'] ?? [] as $tracking) {
            if (strtolower($tracking['Name'] ?? '') === 'nama paket') {
                return $tracking['Option'] ?? null;
            }
        }

        return null;
    }
}