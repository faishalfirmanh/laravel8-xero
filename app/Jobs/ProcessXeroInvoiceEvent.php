<?php

namespace App\Jobs;

use App\Models\Expenses\Purchase\Bill\DBill;
use App\Models\Expenses\Purchase\Bill\PBill;
use App\Models\InvoicesAllFromXero;
use App\Models\ItemsPaketAllFromXero;
use App\Models\MasterData\BankXero;
use App\Models\MasterData\Coa;
use App\Models\MasterData\DataJamaahXero;
use App\Models\MasterData\ItemDetailInvoices;
use App\Models\Transaction\TransactionAllCoa;
use App\Models\Transaction\TransactionNominalBankAccount;
use App\Models\Transaction\VaTransUser;
use App\Services\XeroService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
class ProcessXeroInvoiceEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [10, 60, 300];
    public int $timeout = 120;

    protected array $event;
    private ?array $trackingMap = null;

    public function __construct(array $event)
    {
        $this->event = $event;
    }


    private function waitForXeroSlot(string $tenantId): void
    {
        $key = 'xero_api:last_request_at:' . $tenantId;
        $last = Cache::get($key);
        $now = microtime(true);

        if ($last !== null) {
            $waitUs = 1100000 - (int) (($now - (float) $last) * 1000000);
            if ($waitUs > 0) {
                usleep($waitUs);
            }
        }

        Cache::put($key, microtime(true), 120);
    }
    public function handle(XeroService $xero): void
    {
        Log::info('[XeroWebhook] job mulai', ['resourceId' => $this->event['resourceId'] ?? null]);

        $invoiceId = $this->event['resourceId'] ?? null;
        $tenantId = $this->event['tenantId'] ?? null;

        if (!$invoiceId || !$tenantId) {
            Log::warning('[XeroWebhook] event tanpa resourceId/tenantId, dilewati.');
            return;
        }

        $this->waitForXeroSlot($tenantId);

        try {
            $obj = $xero->getInvoice($tenantId, $invoiceId);
        } catch (\RuntimeException $e) {
            if (stripos($e->getMessage(), '429') !== false) {
                Log::warning('[XeroWebhook] rate limit, release 65s', ['resourceId' => $invoiceId]);
                $this->release(65);
                return;
            }
            throw $e;
        }

        if (empty($obj['InvoiceID'])) {
            Log::warning("[XeroWebhook] respons kosong untuk {$invoiceId}, dilewati.");
            return;
        }

        // Panggilan HTTP dilakukan dulu, di luar transaksi DB
        $paymentDetails = $this->fetchPaymentDetails($xero, $tenantId, $obj);

        DB::transaction(function () use ($obj, $paymentDetails) {
            if (($obj['Type'] ?? null) === 'ACCPAY') {
                $this->syncBill($obj, $paymentDetails);
            } else {
                $this->syncSalesInvoice($obj, $paymentDetails);
            }
        }, 3);

        Log::info('[XeroWebhook] selesai ' . ($obj['InvoiceNumber'] ?? $invoiceId));
    }

    public function failed(\Throwable $e): void
    {
        Log::error('[XeroWebhook] gagal proses event: ' . $e->getMessage());
    }

    // ================================================================
    // SALES INVOICE (ACCREC)
    // ================================================================

    private function syncSalesInvoice(array $inv, array $paymentDetails): void
    {
        $lineItems = $inv['LineItems'] ?? [];
        $firstLine = $lineItems[0] ?? [];
        $issueDate = $this->parseXeroDate($inv['DateString'] ?? $inv['Date'] ?? null);
        $dueDate = $this->parseXeroDate($inv['DueDateString'] ?? $inv['DueDate'] ?? null);
        $contactId = data_get($inv, 'Contact.ContactID');
        $currencyCode = $inv['CurrencyCode'] ?? 'IDR';
        $nominalCurr = $currencyCode === 'SAR' ? $this->sarToIdrRate($inv) : 1;

        $contactRowId = $this->ensureJamaahContact($inv);

        // Invoice lokal punya invoice_uuid acak. Samakan dengan InvoiceID Xero
        // lewat nomor invoice supaya tidak tercipta header kedua.
        if (
            !InvoicesAllFromXero::where('invoice_uuid', $inv['InvoiceID'])->exists()
            && !empty($inv['InvoiceNumber'])
        ) {
            InvoicesAllFromXero::where('invoice_number', $inv['InvoiceNumber'])
                ->update(['invoice_uuid' => $inv['InvoiceID']]);
        }

        InvoicesAllFromXero::upsert(
            [
                [
                    'invoice_uuid' => $inv['InvoiceID'],
                    'invoice_number' => $inv['InvoiceNumber'] ?? null,
                    'invoice_amount' => $inv['AmountPaid'] ?? 0,
                    'invoice_total' => $inv['Total'] ?? 0,
                    'less_nominal' => $inv['AmountDue'] ?? 0,
                    'issue_date' => $issueDate,
                    'due_date' => $dueDate,
                    'status' => $inv['Status'] ?? null,
                    'uuid_contact' => $contactId,
                    'contact_name' => data_get($inv, 'Contact.Name'),
                    'contact_id' => $contactRowId,
                    'uuid_proudct_and_service' => $firstLine['ItemID'] ?? null,
                    'item_name' => $firstLine['Description'] ?? null,
                    'reference' => $inv['Reference'] ?? null,
                    'code_curr' => $currencyCode,
                    'nominal_currency' => $nominalCurr,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            ],
            ['invoice_uuid'],
            [
                'invoice_number',
                'invoice_amount',
                'invoice_total',
                'less_nominal',
                'issue_date',
                'due_date',
                'status',
                'uuid_contact',
                'contact_name',
                'contact_id',
                'uuid_proudct_and_service',
                'item_name',
                'reference',
                'code_curr',
                'nominal_currency',
                'updated_at',
            ]
        );

        $parentId = InvoicesAllFromXero::where('invoice_uuid', $inv['InvoiceID'])->value('id');
        if (!$parentId) {
            return;
        }

        $this->syncVaTrans($inv);

        // ── 0. Buang baris lokal (dibuat dari sistem, belum punya LineItemID) ──
        $localUuids = ItemDetailInvoices::where('parent_inv_id', $parentId)
            ->whereNull('line_item_uuid')
            ->pluck('uuid_detail_inv')
            ->filter()
            ->all();

        if ($localUuids) {
            TransactionAllCoa::whereIn('uuid_detail', $localUuids)->delete();
        }

        ItemDetailInvoices::where('parent_inv_id', $parentId)
            ->whereNull('line_item_uuid')
            ->delete();

        // ── Line item dari Xero ──
        $lineItems = $inv['LineItems'] ?? [];
        $accountCodes = collect($lineItems)->pluck('AccountCode')->filter()->unique()->values()->all();
        $itemCodes = collect($lineItems)->map(fn($l) => data_get($l, 'Item.Code'))->filter()->unique()->values()->all();
        $coaMap = Coa::whereIn('code', $accountCodes)->pluck('id', 'code')->toArray();
        $itemMap = ItemsPaketAllFromXero::whereIn('code', $itemCodes)->pluck('id', 'code')->toArray();

        $rows = [];
        $liveIds = [];

        foreach ($lineItems as $line) {
            $lineId = $line['LineItemID'] ?? null;
            if (!$lineId) {
                continue;
            }

            [$paketUuid, $divisiUuid] = $this->resolveTrackingFromLine($line);
            $itemCode = data_get($line, 'Item.Code');

            $rows[] = [
                'invoice_number' => $inv['InvoiceNumber'] ?? null,
                'uuid_invoices' => $inv['InvoiceID'],
                'uuid_item' => data_get($line, 'Item.ItemID') ?? ($line['ItemID'] ?? 'no_set'),
                'qty' => $line['Quantity'] ?? 0,
                'unit_price' => $line['UnitAmount'] ?? 0,
                'total_amount_each_row' => $line['LineAmount'] ?? 0,
                'line_item_uuid' => $lineId,
                'coa_id' => isset($line['AccountCode']) ? ($coaMap[$line['AccountCode']] ?? null) : null,
                'parent_inv_id' => $parentId,
                'item_id' => $itemCode ? ($itemMap[$itemCode] ?? null) : null,
                'uuid_detail_inv' => $lineId,
                'paket_tracking_uuid' => $paketUuid,
                'divisi_travel_tracking_uuid' => $divisiUuid,
                'desc' => $line['Description'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $liveIds[] = $lineId;
        }

        // ── 1. Line yang sudah dihapus di Xero ──
        if ($liveIds) {
            $staleUuids = ItemDetailInvoices::where('parent_inv_id', $parentId)
                ->whereNotIn('line_item_uuid', $liveIds)
                ->pluck('uuid_detail_inv')
                ->all();

            if ($staleUuids) {
                TransactionAllCoa::whereIn('uuid_detail', $staleUuids)->delete();
            }

            ItemDetailInvoices::where('parent_inv_id', $parentId)
                ->whereNotIn('line_item_uuid', $liveIds)
                ->delete();
        } else {
            // Xero tidak mengirim line sama sekali: hapus semua line invoice ini
            $allUuids = ItemDetailInvoices::where('parent_inv_id', $parentId)
                ->pluck('uuid_detail_inv')->all();

            if ($allUuids) {
                TransactionAllCoa::whereIn('uuid_detail', $allUuids)->delete();
            }
            ItemDetailInvoices::where('parent_inv_id', $parentId)->delete();
        }

        // ── 2. Upsert line dari Xero ──
        if ($rows) {
            ItemDetailInvoices::upsert(
                $rows,
                ['line_item_uuid'],
                [
                    'invoice_number',
                    'uuid_invoices',
                    'uuid_item',
                    'qty',
                    'unit_price',
                    'total_amount_each_row',
                    'coa_id',
                    'parent_inv_id',
                    'item_id',
                    'uuid_detail_inv',
                    'paket_tracking_uuid',
                    'divisi_travel_tracking_uuid',
                    'desc',
                    'updated_at',
                ]
            );
        }
        // ── Jurnal (transaction_all_coas) ──
        $postable = in_array($inv['Status'] ?? null, ['AUTHORISED', 'PAID'], true);

        if (!$postable) {
            if ($liveIds) {
                TransactionAllCoa::whereIn('uuid_detail', $liveIds)->delete();
            }
        } else {
            foreach ($rows as $r) {
                if (empty($r['coa_id'])) {
                    continue;
                }

                TransactionAllCoa::updateOrCreate(
                    ['uuid_detail' => $r['uuid_detail_inv']],
                    [
                        'date_transaction' => $issueDate,
                        'uuid_coa' => $r['coa_id'],
                        'reference' => $inv['Reference'] ?? '-',
                        'is_speend' => 0,
                        'nominal' => $r['total_amount_each_row'],
                        'code_curr' => $currencyCode,
                        'nominal_currency' => $nominalCurr,
                        'base_nominal' => (float) $nominalCurr * (float) $r['total_amount_each_row'],
                    ]
                );
            }
        }

        $this->savePayments($paymentDetails, $parentId, false, $currencyCode);
    }

    // ================================================================
    // BILL (ACCPAY)
    // ================================================================

    private function syncBill(array $inv, array $paymentDetails): void
    {
        $lineItems = $inv['LineItems'] ?? [];
        $issueDate = $this->parseXeroDate($inv['DateString'] ?? $inv['Date'] ?? null);
        $dueDate = $this->parseXeroDate($inv['DueDateString'] ?? $inv['DueDate'] ?? null);
        $contactId = data_get($inv, 'Contact.ContactID');
        $currencyCode = $inv['CurrencyCode'] ?? 'IDR';

        $fromId = DataJamaahXero::where('uuid_contact', $contactId)->value('id') ?? 1;

        if (
            !PBill::where('bills_uuid_xero', $inv['InvoiceID'])->exists()
            && !empty($inv['InvoiceNumber'])
        ) {
            PBill::where('reference', $inv['InvoiceNumber'])
                ->update(['bills_uuid_xero' => $inv['InvoiceID']]);
        }
        
        $invNo = trim((string) ($inv['InvoiceNumber'] ?? ''));

if ($invNo !== ''
    && !PBill::where('bills_uuid_xero', $inv['InvoiceID'])->exists()) {
    PBill::whereRaw('LOWER(TRIM(reference)) = ?', [mb_strtolower($invNo)])
        ->whereNull('bills_uuid_xero')
        ->orderBy('id')
        ->limit(1)
        ->update(['bills_uuid_xero' => $inv['InvoiceID']]);
}

        PBill::upsert(
            [
                [
                    'bills_uuid_xero' => $inv['InvoiceID'],
                    'uuid_from' => $fromId,
                    'date_req' => $issueDate,
                    'due_date' => $dueDate,
                    'reference' => $inv['InvoiceNumber'] ?? null,
                    'amounts_are' => $this->mapAmountsAre($inv['LineAmountTypes'] ?? null),
                    'subtotal' => $inv['SubTotal'] ?? 0,
                    'total' => $inv['Total'] ?? 0,
                    'tax' => $inv['TotalTax'] ?? 0,
                    'nominal_paid' => $inv['AmountPaid'] ?? 0,
                    'nominal_due' => $inv['AmountDue'] ?? 0,
                    'status' => $this->mapBillStatus($inv['Status'] ?? null),
                    'currency' => $currencyCode,
                    'created_by' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            ],
            ['bills_uuid_xero'],
            [
                'uuid_from',
                'date_req',
                'due_date',
                'reference',
                'amounts_are',
                'subtotal',
                'total',
                'tax',
                'nominal_paid',
                'nominal_due',
                'status',
                'currency',
                'updated_at',
            ]
        );

        $parentId = PBill::where('bills_uuid_xero', $inv['InvoiceID'])->value('id');
        if (!$parentId) {
            return;
        }

        $accountCodes = collect($lineItems)->pluck('AccountCode')->filter()->unique()->values()->all();
        $coaMap = Coa::whereIn('code', $accountCodes)->pluck('id', 'code')->toArray();

        $rows = [];
        $liveIds = [];

        foreach ($lineItems as $line) {
            $uuidDetail = $line['LineItemID'] ?? null;
            if (!$uuidDetail) {
                continue;
            }

            [$paketUuid, $divisiUuid] = $this->resolveTrackingFromLine($line);

            $rows[] = [
                'bills_parent_id' => $parentId,
                'item_code' => $line['ItemCode'] ?? data_get($line, 'Item.Code'),
                'desc' => $line['Description'] ?? null,
                'qty' => $line['Quantity'] ?? 0,
                'unit_price' => $line['UnitAmount'] ?? 0,
                'account_id_coa' => isset($line['AccountCode']) ? ($coaMap[$line['AccountCode']] ?? null) : null,
                'tax_rate' => $line['TaxAmount'] ?? 0,
                'paket_tracking_uuid' => $paketUuid,
                'divisi_travel_tracking_uuid' => $divisiUuid,
                'amount' => $line['LineAmount'] ?? 0,
                'uuid_detail' => $uuidDetail,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $liveIds[] = $uuidDetail;
        }

        // ── 0. Buang baris lokal yang tidak punya LineItemID Xero ──
        // (Di DBill, uuid_detail lokal acak, jadi dicari lewat bills_parent_id
        // dan dibandingkan dengan daftar LineItemID dari Xero)
        $allLocal = DBill::where('bills_parent_id', $parentId)
            ->pluck('uuid_detail')->all();

        $localOnly = array_values(array_diff($allLocal, $liveIds));

        if ($localOnly) {
            TransactionAllCoa::whereIn('uuid_detail', $localOnly)->delete();

            DBill::where('bills_parent_id', $parentId)
                ->whereIn('uuid_detail', $localOnly)
                ->delete();
        }

        // ── 1. Upsert line dari Xero ──
        if ($rows) {
            DBill::upsert(
                $rows,
                ['uuid_detail'],
                [
                    'bills_parent_id',
                    'item_code',
                    'desc',
                    'qty',
                    'unit_price',
                    'account_id_coa',
                    'tax_rate',
                    'paket_tracking_uuid',
                    'divisi_travel_tracking_uuid',
                    'amount',
                    'updated_at',
                ]
            );
        }

        $postable = in_array($inv['Status'] ?? null, ['AUTHORISED', 'PAID'], true);

        if (!$postable) {
            if ($liveIds) {
                TransactionAllCoa::whereIn('uuid_detail', $liveIds)->delete();
            }
        } else {
            foreach ($rows as $r) {
                if (empty($r['account_id_coa'])) {
                    continue;
                }

                TransactionAllCoa::updateOrCreate(
                    ['uuid_detail' => $r['uuid_detail']],
                    [
                        'date_transaction' => $issueDate,
                        'uuid_coa' => $r['account_id_coa'],
                        'reference' => $inv['Reference'] ?? '-',
                        'is_speend' => 1, // pengeluaran
                        'nominal' => $r['amount'],
                        'code_curr' => $currencyCode,
                        'nominal_currency' => 1,
                        'base_nominal' => $r['amount'],
                    ]
                );
            }
        }

        $this->savePayments($paymentDetails, $parentId, true, $currencyCode);
    }

    // ================================================================
    // PAYMENT
    // ================================================================

    private function fetchPaymentDetails(XeroService $xero, string $tenantId, array $obj): array
    {
        $details = [];

        foreach ($obj['Payments'] ?? [] as $row) {
            $pid = $row['PaymentID'] ?? null;
            if (!$pid) {
                continue;
            }

            try {
                $p = $xero->getPayment($tenantId, $pid);
            } catch (\Throwable $e) {
                Log::warning("[XeroWebhook] gagal fetch payment {$pid}: " . $e->getMessage());
                continue;
            }

            $p = $p['Payments'][0] ?? $p;

            if (!empty($p['PaymentID'])) {
                $details[] = $p;
            }
        }

        return $details;
    }

    private function savePayments(array $payments, int $parentId, bool $isBill, string $currencyCode): void
    {
        foreach ($payments as $p) {
            $paymentId = $p['PaymentID'];
            $accountCode = data_get($p, 'Account.Code');

            if (!$accountCode) {
                Log::warning("[XeroWebhook] payment {$paymentId}: AccountCode kosong, dilewati.");
                continue;
            }

            $bank = BankXero::where('code', $accountCode)->first();
            if (!$bank) {
                Log::warning("[XeroWebhook] payment {$paymentId}: kode bank '{$accountCode}' tidak ditemukan.");
                continue;
            }

            $amount = (float) ($p['Amount'] ?? 0);
            $currencyRate = (float) ($p['CurrencyRate'] ?? 0);
            $nominalCurr = 1;
            $totalBase = $amount;

            if ($currencyCode === 'SAR' && $currencyRate > 0) {
                $nominalCurr = number_format(1 / $currencyRate, 2, '.', '');
                $totalBase = round($amount / $currencyRate, 0);
            }

            TransactionNominalBankAccount::updateOrCreate(
                ['payment_uuid' => $paymentId],
                [
                    'uuid_bank' => $bank->id,
                    'nominal_receive' => $isBill ? 0 : $amount,
                    'nominal_spend' => $isBill ? $amount : 0,
                    'nominal_transfer' => 0,
                    'total_base_receive' => $isBill ? 0 : $totalBase,
                    'total_base_spend' => $isBill ? $totalBase : 0,
                    'nominal_currency' => $nominalCurr,
                    'created_by' => 1,
                    'date_transaction' => $this->parseXeroDate($p['Date'] ?? null),
                    'reference_detail' => $p['Reference'] ?? null,
                    'id_parent_invoice' => $isBill ? null : $parentId,
                    'id_parent_bill' => $isBill ? $parentId : null,
                ]
            );
        }
    }

    // ================================================================
    // KONTAK & VA
    // ================================================================

    private function ensureJamaahContact(array $inv): int
    {
        $contactId = data_get($inv, 'Contact.ContactID');

        $existingId = DataJamaahXero::where('uuid_contact', $contactId)->value('id');
        if ($existingId) {
            return (int) $existingId;
        }

        $phones = $inv['Contact']['Phones'] ?? [];
        $mobile = collect($phones)->firstWhere('PhoneType', 'MOBILE');
        $phone = $mobile['PhoneNumber'] ?? ($phones[0]['PhoneNumber'] ?? null);

        $addresses = $inv['Contact']['Addresses'] ?? [];
        $street = collect($addresses)->firstWhere('AddressType', 'STREET');
        $address = $street['AddressLine1'] ?? ($addresses[0]['AddressLine1'] ?? null);

        return (int) DataJamaahXero::create([
            'uuid_contact' => $contactId,
            'full_name' => data_get($inv, 'Contact.Name'),
            'phone_number' => $phone,
            'detail_address' => $address,
        ])->id;
    }

    private function syncVaTrans(array $inv): void
    {
        $vaNumber = null;
        $bankName = null;
        $paketName = null;
        $totPayment = 0.0;
        $totNominal = 0.0;

        foreach ($inv['LineItems'] ?? [] as $item) {
            $lineAmount = (float) ($item['LineAmount'] ?? 0);
            $vaInfo = $this->extractVaInfo($item['Description'] ?? '');

            if ($vaInfo && $vaNumber === null) {
                $vaNumber = $vaInfo['va_number'];
                $bankName = $vaInfo['bank_name'];
                $paketName = $this->extractPaketName($item) ?? '--';
            }

            if ($lineAmount < 0) {
                $totPayment += abs($lineAmount);
            } elseif ($lineAmount > 0) {
                $totNominal += $lineAmount;
            }
        }

        if ($vaNumber === null) {
            return;
        }

        $existing = VaTransUser::where('va_number', $vaNumber)->first();

        if ($existing) {
            $existing->update([
                'payment' => $totPayment,
                'total_nominal' => $totNominal,
            ]);
            return;
        }

        VaTransUser::create([
            'inv_number' => $inv['InvoiceNumber'] ?? null,
            'va_number' => $vaNumber,
            'paket_name' => $paketName,
            'bank_name' => $bankName,
            'name_contact' => data_get($inv, 'Contact.Name'),
            'payment' => $totPayment,
            'total_nominal' => $totNominal,
        ]);
    }

    protected function extractVaInfo(string $description): ?array
    {
        $upper = strtoupper($description);

        if (!preg_match('/\b(?:VIRTUAL\s*ACCOUNT|VA)\b\s*[:\-]?\s*(.+)$/', $upper, $matches)) {
            return null;
        }

        $remainder = trim($matches[1]);

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

    // ================================================================
    // HELPER
    // ================================================================

    /**
     * Ambil UUID Nama Paket & Divisi dari Tracking line item Xero.
     * Nama dicocokkan dengan item_name_category di tabel tracking_categories,
     * lalu yang disimpan adalah item_uuid_category.
     */
    private function resolveTrackingFromLine(array $line): array
    {
        $paket = null;
        $divisi = null;

        foreach ($line['Tracking'] ?? [] as $t) {
            $cat = $this->normTrackName($t['Name'] ?? '');
            $opt = $t['Option'] ?? '';

            if ($cat === 'nama paket') {
                $paket = $this->lookupTrackingUuid('Nama Paket', $opt);
            } elseif ($cat === 'divisi') {
                $divisi = $this->lookupTrackingUuid('Divisi', $opt);
            }
        }

        return [$paket, $divisi];
    }

    /**
     * Cari item_uuid_category berdasarkan parent & item_name_category.
     * Map dibangun sekali per job dari tabel tracking_categories (tabel kecil).
     */
    private function lookupTrackingUuid(string $parentName, string $optionName): ?string
    {
        $parentKey = $this->normTrackName($parentName);
        $nameKey = $this->normTrackName($optionName);

        $uuid = $this->trackingMap()[$parentKey][$nameKey] ?? null;

        if ($uuid === null && $nameKey !== '') {
            Log::warning('[XeroWebhook] tracking tidak ditemukan', [
                'parent' => $parentName,
                'option' => $optionName,
            ]);
        }

        return $uuid;
    }

    private function trackingMap(): array
    {
        if ($this->trackingMap !== null) {
            return $this->trackingMap;
        }

        $this->trackingMap = [];

        foreach (DB::table('tracking_categories')->get() as $row) {
            $parentKey = $this->normTrackName($row->name_parent_category);
            $lines = json_decode($row->lines_category, true) ?: [];

            foreach ($lines as $line) {
                $nameKey = $this->normTrackName($line['item_name_category'] ?? '');
                $uuid = $line['item_uuid_category'] ?? null;

                if ($nameKey !== '' && $uuid !== null) {
                    $this->trackingMap[$parentKey][$nameKey] = $uuid;
                }
            }
        }

        return $this->trackingMap;
    }

    /**
     * Normalisasi nama: huruf kecil, spasi ganda/tab/newline jadi satu spasi, di-trim.
     */
    private function normTrackName(?string $s): string
    {
        return trim(preg_replace('/\s+/u', ' ', mb_strtolower((string) $s, 'UTF-8')));
    }

    private function sarToIdrRate(array $inv): string
    {
        $rate = (float) ($inv['CurrencyRate'] ?? 0);

        return $rate > 0 ? number_format(1 / $rate, 2, '.', '') : '1.00';
    }

    private function mapBillStatus(?string $xeroStatus): int
    {
        switch ($xeroStatus) {
            case 'PAID':
                return 2;
            case 'SUBMITTED':
            case 'AUTHORISED':
                return 1;
            default:
                return 0;
        }
    }

    private function mapAmountsAre(?string $lineAmountTypes): int
    {
        switch ($lineAmountTypes) {
            case 'Inclusive':
                return 1;
            case 'NoTax':
                return 0;
            default:
                return 2;
        }
    }

    private function parseXeroDate(?string $dateStr): ?string
    {
        if (!$dateStr) {
            return null;
        }

        if (strpos($dateStr, 'T') !== false || strpos($dateStr, '-') !== false) {
            try {
                return Carbon::parse($dateStr)->format('Y-m-d');
            } catch (\Exception $e) {
                Log::warning("[parseXeroDate] gagal parse: $dateStr");
                return null;
            }
        }

        if (preg_match('/\/Date\((\d+)/', $dateStr, $m)) {
            return Carbon::createFromTimestampMs((int) $m[1])->format('Y-m-d');
        }

        Log::warning("[parseXeroDate] format tidak dikenali: $dateStr");
        return null;
    }
}