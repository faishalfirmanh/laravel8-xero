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
use App\Models\User;
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

    /**
     * Naikkan angka ini kalau logika sync berubah dan semua invoice perlu
     * di-sync ulang sekali (fingerprint lama otomatis dianggap beda).
     */
    private const FINGERPRINT_VERSION = 1;

    public int $tries = 3;
    public array $backoff = [10, 60, 300];
    public int $timeout = 120;

    protected array $event;
    private ?array $trackingMap = null;

    /**
     * true kalau ada bagian yang terlewat saat sync (bank/COA/tracking belum ada,
     * payment gagal di-fetch). Fingerprint tidak disimpan supaya event berikutnya
     * mencoba lagi.
     */
    private bool $syncIncomplete = false;

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

        $isBill = ($obj['Type'] ?? null) === 'ACCPAY';

        // Xero (New Invoicing) auto-save invoice DRAFT: invoice yang hanya DIBUKA pun
        // tercatat "Edited / INV-xxx updated" di history dan memicu webhook UPDATE.
        // UpdatedDateUTC ikut berubah, jadi tidak bisa dipakai sebagai pembanding.
        // Yang dibandingkan adalah isi data yang kita simpan (fingerprint).
        $fingerprint = $this->fingerprint($obj);

        if ($this->storedFingerprint($obj, $isBill) === $fingerprint) {
            Log::info('[XeroWebhook] tidak ada perubahan data, dilewati ' . ($obj['InvoiceNumber'] ?? $invoiceId));
            return;
        }

        $this->syncIncomplete = false;

        // Panggilan HTTP dilakukan dulu, di luar transaksi DB
        $paymentDetails = $this->fetchPaymentDetails($xero, $tenantId, $obj);
        $updatedByName = $this->resolveUpdatedByName($xero, $tenantId, $invoiceId);

        DB::transaction(function () use ($obj, $paymentDetails, $updatedByName, $isBill, $fingerprint) {
            if ($isBill) {
                $this->syncBill($obj, $paymentDetails, $updatedByName, $fingerprint);
            } else {
                $this->syncSalesInvoice($obj, $paymentDetails, $updatedByName, $fingerprint);
            }

            // Ada bagian yang terlewat: jangan simpan fingerprint, biar event berikutnya mencoba lagi.
            if ($this->syncIncomplete) {
                $this->forgetFingerprint($obj, $isBill);
            }
        }, 3);

        Log::info('[XeroWebhook] selesai ' . ($obj['InvoiceNumber'] ?? $invoiceId));
    }

    public function failed(\Throwable $e): void
    {
        Log::error('[XeroWebhook] gagal proses event: ' . $e->getMessage());
    }

    // ================================================================
    // FINGERPRINT (deteksi "tidak ada perubahan")
    // ================================================================

    /**
     * Hash dari semua field Xero yang benar-benar kita simpan ke database.
     * Sengaja TIDAK memasukkan UpdatedDateUTC, HasAttachments, SentToContact, dsb.
     */
    private function fingerprint(array $inv): string
    {
        $lines = [];

        foreach ($inv['LineItems'] ?? [] as $l) {
            $tracking = [];
            foreach ($l['Tracking'] ?? [] as $t) {
                $tracking[] = [
                    $this->normTrackName($t['Name'] ?? ''),
                    (string) ($t['Option'] ?? ''),
                ];
            }

            $lines[] = [
                $l['LineItemID'] ?? null,
                $l['Description'] ?? null,
                $l['Quantity'] ?? null,
                $l['UnitAmount'] ?? null,
                $l['LineAmount'] ?? null,
                $l['TaxAmount'] ?? null,
                $l['AccountCode'] ?? null,
                data_get($l, 'Item.ItemID'),
                data_get($l, 'Item.Code') ?? ($l['ItemCode'] ?? null),
                $tracking,
            ];
        }

        $payments = [];
        foreach ($inv['Payments'] ?? [] as $p) {
            $payments[(string) ($p['PaymentID'] ?? '')] = [
                $p['Amount'] ?? null,
                $p['Date'] ?? null,
            ];
        }
        ksort($payments);

        $payload = [
            self::FINGERPRINT_VERSION,
            $inv['InvoiceID'] ?? null,
            $inv['InvoiceNumber'] ?? null,
            $inv['Type'] ?? null,
            $inv['Status'] ?? null,
            $inv['Reference'] ?? null,
            $inv['DateString'] ?? ($inv['Date'] ?? null),
            $inv['DueDateString'] ?? ($inv['DueDate'] ?? null),
            data_get($inv, 'Contact.ContactID'),
            data_get($inv, 'Contact.Name'),
            $inv['CurrencyCode'] ?? null,
            $inv['CurrencyRate'] ?? null,
            $inv['LineAmountTypes'] ?? null,
            $inv['SubTotal'] ?? null,
            $inv['TotalTax'] ?? null,
            $inv['Total'] ?? null,
            $inv['AmountPaid'] ?? null,
            $inv['AmountDue'] ?? null,
            $lines,
            $payments,
        ];

        return sha1((string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR));
    }

    private function storedFingerprint(array $inv, bool $isBill): ?string
    {
        $value = $isBill
            ? PBill::where('bills_uuid_xero', $inv['InvoiceID'])->value('xero_hash')
            : InvoicesAllFromXero::where('invoice_uuid', $inv['InvoiceID'])->value('xero_hash');

        return $value ? (string) $value : null;
    }

    /**
     * Kosongkan fingerprint tanpa menyentuh updated_at (toBase() menghindari
     * auto-timestamp Eloquent).
     */
    private function forgetFingerprint(array $inv, bool $isBill): void
    {
        if ($isBill) {
            PBill::where('bills_uuid_xero', $inv['InvoiceID'])
                ->toBase()
                ->update(['xero_hash' => null]);
            return;
        }

        InvoicesAllFromXero::where('invoice_uuid', $inv['InvoiceID'])
            ->toBase()
            ->update(['xero_hash' => null]);
    }

    // ================================================================
    // SALES INVOICE (ACCREC)
    // ================================================================

    /**
     * Cari nama user lokal dari user Xero yang terakhir mengubah invoice.
     * Return null jika tidak ketemu (nilai lama di DB dipertahankan).
     */
    private function resolveUpdatedByName(XeroService $xero, string $tenantId, string $invoiceId): ?string
    {
        try {
            $this->waitForXeroSlot($tenantId);
            $records = $xero->getInvoiceHistory($tenantId, $invoiceId);

            // Ambil entri terbaru yang punya nama user
            $latest = collect($records)
                ->filter(fn($r) => !empty($r['User']))
                ->sortByDesc(fn($r) => $this->historyTimestamp($r['DateUTC'] ?? $r['DateUTCString'] ?? ''))
                ->first();

            $xeroName = trim((string) ($latest['User'] ?? ''));
            if ($xeroName === '') {
                return null;
            }

            // Daftar user Xero di-cache 1 jam supaya tidak boros rate limit
            $users = Cache::remember("xero_users:{$tenantId}", 3600, function () use ($xero, $tenantId) {
                $this->waitForXeroSlot($tenantId);
                return $xero->getUsers($tenantId);
            });

            $needle = $this->normName($xeroName);

            $xeroUser = collect($users)->first(function ($u) use ($needle) {
                $full = $this->normName(($u['FirstName'] ?? '') . ' ' . ($u['LastName'] ?? ''));
                return $full === $needle || $this->normName($u['EmailAddress'] ?? '') === $needle;
            });

            if (!$xeroUser || empty($xeroUser['UserID'])) {
                Log::warning('[XeroWebhook] user Xero tidak ketemu di /Users', ['user' => $xeroName]);
                return null;
            }

            // Cari user lokal berdasarkan ID Xero yang sudah didaftarkan
            $localName = DB::table('users')
                ->where('uuid_unique_id_xero', $xeroUser['UserID'])
                ->value('name');

            if (!$localName) {
                Log::warning('[XeroWebhook] UserID Xero belum terdaftar di users lokal', [
                    'xero_user_id' => $xeroUser['UserID'],
                    'user' => $xeroName,
                ]);
            }

            return $localName ?: null;

        } catch (\Throwable $e) {
            // Gagal ambil nama tidak boleh menggagalkan sinkronisasi invoice
            Log::warning('[XeroWebhook] resolveUpdatedByName gagal: ' . $e->getMessage());
            return null;
        }
    }

    private function normName(?string $s): string
    {
        return trim(preg_replace('/\s+/u', ' ', mb_strtolower((string) $s, 'UTF-8')));
    }

    /** Parse "/Date(1234567890000+0000)/" atau ISO string ke timestamp untuk sorting. */
    private function historyTimestamp(string $raw): int
    {
        if (preg_match('/\/Date\((\d+)/', $raw, $m)) {
            return (int) $m[1];
        }
        $ts = strtotime($raw);
        return $ts === false ? 0 : $ts * 1000;
    }

    private function syncSalesInvoice(
        array $inv,
        array $paymentDetails,
        ?string $updatedByName = null,
        ?string $fingerprint = null
    ): void {
        $lineItems = $inv['LineItems'] ?? [];
        $firstLine = $lineItems[0] ?? [];
        $issueDate = $this->parseXeroDate($inv['DateString'] ?? $inv['Date'] ?? null);
        $dueDate = $this->parseXeroDate($inv['DueDateString'] ?? $inv['DueDate'] ?? null);
        $contactId = data_get($inv, 'Contact.ContactID');
        $currencyCode = $inv['CurrencyCode'] ?? 'IDR';
        $nominalCurr = $this->fxToIdr($currencyCode, (float) ($inv['CurrencyRate'] ?? 0));

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

        $existingName = InvoicesAllFromXero::where('invoice_uuid', $inv['InvoiceID'])
            ->value('created_by_name');
        $userName = $updatedByName ?: $existingName;

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
                    'created_by_name' => $userName,
                    'xero_hash' => $fingerprint,
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
                'created_by_name',
                'xero_hash',
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
        $accountCodes = collect($lineItems)->pluck('AccountCode')->filter()->unique()->values()->all();
        $coaMap = Coa::whereIn('code', $accountCodes)->pluck('id', 'code')->toArray();
        $itemMap = $this->resolveItemMaster($lineItems, $coaMap);

        $rows = [];
        $liveIds = [];

        foreach ($lineItems as $line) {
            $lineId = $line['LineItemID'] ?? null;
            if (!$lineId) {
                continue;
            }

            [$paketUuid, $divisiUuid] = $this->resolveTrackingFromLine($line);
            $itemUuid = data_get($line, 'Item.ItemID');

            $accountCode = $line['AccountCode'] ?? null;
            if ($accountCode !== null && $accountCode !== '' && !isset($coaMap[$accountCode])) {
                // COA belum ada di master lokal: jurnal baris ini terlewat
                $this->syncIncomplete = true;
            }

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
                'item_id' => $itemUuid ? ($itemMap[$itemUuid] ?? null) : null,
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

        $this->savePayments($paymentDetails, $parentId, false, $currencyCode, (float) ($inv['CurrencyRate'] ?? 1));
    }

    // ================================================================
    // BILL (ACCPAY)
    // ================================================================

    /**
     * Nilai nominal_currency: IDR = 1, SAR = 1 / CurrencyRate (IDR per 1 SAR).
     */
    private function fxToIdr(string $currency, float $rate): float
    {
        if (strtoupper($currency) === 'IDR') {
            return 1.0;
        }

        if ($rate <= 0) {
            throw new \InvalidArgumentException("CurrencyRate invalid untuk {$currency}: {$rate}");
        }

        return round(1 / $rate, 4);
    }

    public function createDataJamaah(?array $contact): ?int
    {
        $contactId = $contact['ContactID'] ?? null;

        if (!$contactId) {
            return null;
        }

        $phones = $contact['Phones'] ?? [];
        $mobile = collect($phones)->firstWhere('PhoneType', 'MOBILE');
        $phone = $mobile['PhoneNumber'] ?? ($phones[0]['PhoneNumber'] ?? null);

        $addresses = $contact['Addresses'] ?? [];
        $street = collect($addresses)->firstWhere('AddressType', 'STREET');
        $address = $street['AddressLine1'] ?? ($addresses[0]['AddressLine1'] ?? null);

        // Argumen pertama harus array asosiatif [kolom => nilai].
        // Sebelumnya ['uuid_contact'] (tanpa key) sehingga query mencari kolom "0".
        $saved = DataJamaahXero::firstOrCreate(
            ['uuid_contact' => $contactId],
            [
                'full_name' => $contact['Name'] ?? null,
                'phone_number' => $phone,
                'detail_address' => $address,
            ]
        );

        return (int) $saved->id;
    }

    /**
     * Pastikan setiap Item di line invoice punya baris di items_paket_all_from_xeros.
     * Return: [uuid_proudct_and_service => id]
     */
    private function resolveItemMaster(array $lineItems, array $coaMap): array
    {
        $itemUuids = collect($lineItems)
            ->map(fn($l) => data_get($l, 'Item.ItemID'))
            ->filter()->unique()->values()->all();

        if (!$itemUuids) {
            return [];
        }

        // 1 query untuk semua item yang sudah ada
        $itemMap = ItemsPaketAllFromXero::whereIn('uuid_proudct_and_service', $itemUuids)
            ->pluck('id', 'uuid_proudct_and_service')
            ->toArray();

        foreach ($lineItems as $line) {
            $itemUuid = data_get($line, 'Item.ItemID');
            if (!$itemUuid || isset($itemMap[$itemUuid])) {
                continue;
            }

            $code = data_get($line, 'Item.Code') ?: $itemUuid;
            $name = data_get($line, 'Item.Name') ?: ($line['Description'] ?? $code);

            // Opsional: item buatan web (uuid 'from_web') dengan code sama -> pakai baris itu
            // supaya tidak dobel, lalu isi uuid-nya dengan ItemID Xero.
            $local = ItemsPaketAllFromXero::where('code', $code)
                ->where('uuid_proudct_and_service', 'from_web')
                ->first();

            if ($local) {
                $local->update(['uuid_proudct_and_service' => $itemUuid]);
                $itemMap[$itemUuid] = $local->id;
                continue;
            }

            $accountCode = $line['AccountCode'] ?? null;

            // firstOrCreate: aman kalau webhook yang sama masuk dua kali bersamaan
            $item = ItemsPaketAllFromXero::firstOrCreate(
                ['uuid_proudct_and_service' => $itemUuid],
                [
                    'code' => mb_substr((string) $code, 0, 255),
                    'nama_paket' => mb_substr((string) $name, 0, 255),
                    'sales_AccountCode' => $accountCode,
                    'purchase_AccountCode' => null,
                    'total_hari' => 0,
                    'jenis_item' => 1,
                    'price_purchase' => 0,
                    'price_sales' => $line['UnitAmount'] ?? 0,
                    'desc' => isset($line['Description']) ? mb_substr($line['Description'], 0, 255) : null,
                    'account_id_salles' => $accountCode ? ($coaMap[$accountCode] ?? null) : null,
                    'tax_rate_salles' => 0,     // NOT NULL tanpa default
                    'tax_rate_purchase' => 0,   // NOT NULL tanpa default
                    'is_item_product' => 0,
                ]
            );

            $itemMap[$itemUuid] = $item->id;
        }

        return $itemMap;
    }

    private function syncBill(
        array $inv,
        array $paymentDetails,
        ?string $updatedByName = null,
        ?string $fingerprint = null
    ): void {
        $lineItems = $inv['LineItems'] ?? [];
        $issueDate = $this->parseXeroDate($inv['DateString'] ?? $inv['Date'] ?? null);
        $dueDate = $this->parseXeroDate($inv['DueDateString'] ?? $inv['DueDate'] ?? null);
        $contactId = data_get($inv, 'Contact.ContactID');
        $currencyCode = $inv['CurrencyCode'] ?? 'IDR';

        $fromId = DataJamaahXero::where('uuid_contact', $contactId)->value('id')
            ?? $this->createDataJamaah($inv['Contact'] ?? null)
            ?? 1;

        // Samakan bill lokal dengan InvoiceID Xero lewat reference.
        // Hanya satu baris yang belum punya uuid Xero (blok lama yang meng-update
        // SEMUA bill dengan reference sama sudah dihapus: reference di bill Xero
        // tidak dijamin unik, bisa menimpa uuid bill lain).
        $invNo = trim((string) ($inv['InvoiceNumber'] ?? ''));

        if (
            $invNo !== ''
            && !PBill::where('bills_uuid_xero', $inv['InvoiceID'])->exists()
        ) {
            PBill::whereRaw('LOWER(TRIM(reference)) = ?', [mb_strtolower($invNo)])
                ->whereNull('bills_uuid_xero')
                ->orderBy('id')
                ->limit(1)
                ->update(['bills_uuid_xero' => $inv['InvoiceID']]);
        }

        $existingName = PBill::where('bills_uuid_xero', $inv['InvoiceID'])
            ->value('created_by_name');
        $userName = $updatedByName ?: $existingName;
        $fx = $this->fxToIdr($currencyCode, (float) ($inv['CurrencyRate'] ?? 0));

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
                    'total_base' => round((float) ($inv['Total'] ?? 0) * $fx, 2),
                    'created_by_name' => $userName,
                    'nominal_currency' => $fx,
                    'nominal_paid_base' => round((float) ($inv['AmountPaid'] ?? 0) * $fx, 2),
                    'nominal_due_base' => round((float) ($inv['AmountDue'] ?? 0) * $fx, 2),
                    'xero_hash' => $fingerprint,
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
                'created_by_name',
                'total_base',
                'nominal_paid_base',
                'nominal_due_base',
                'xero_hash',
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
        $itemCache = [];

        foreach ($lineItems as $line) {
            $uuidDetail = $line['LineItemID'] ?? null;
            if (!$uuidDetail) {
                continue;
            }

            [$paketUuid, $divisiUuid] = $this->resolveTrackingFromLine($line);

            $itemId = $this->resolveBillItemMaster($line, $itemCache);
            $itemCode = trim((string) ($line['ItemCode'] ?? data_get($line, 'Item.Code') ?? '')) ?: null;

            $accountCode = $line['AccountCode'] ?? null;
            if ($accountCode !== null && $accountCode !== '' && !isset($coaMap[$accountCode])) {
                // COA belum ada di master lokal: jurnal baris ini terlewat
                $this->syncIncomplete = true;
            }

            $rows[] = [
                'bills_parent_id' => $parentId,
                'item_code' => $itemCode,
                'item_id_prod_serv' => $itemId,
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
                'total_base' => (int) round((float) ($line['LineAmount'] ?? 0) * $fx, 0),
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
                    'item_id_prod_serv',
                    'tax_rate',
                    'paket_tracking_uuid',
                    'divisi_travel_tracking_uuid',
                    'amount',
                    'total_base',
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
            $nominalCurr = $fx;
            foreach ($rows as $r) {
                if (empty($r['account_id_coa'])) {
                    continue;
                }

                TransactionAllCoa::updateOrCreate(
                    ['uuid_detail' => $r['uuid_detail']],
                    [
                        'date_transaction' => $issueDate,
                        'uuid_coa' => $r['account_id_coa'],
                        'reference' => $inv['InvoiceNumber'] ?? '-',
                        'is_speend' => 1,
                        'nominal' => $r['amount'],
                        'code_curr' => $currencyCode,
                        'nominal_currency' => $nominalCurr,
                        'base_nominal' => round((float) $r['amount'] * $nominalCurr, 2),
                    ]
                );
            }
        }

        $this->savePayments($paymentDetails, $parentId, true, $currencyCode, (float) ($inv['CurrencyRate'] ?? 1));
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
                $this->syncIncomplete = true;
                continue;
            }

            $p = $p['Payments'][0] ?? $p;

            if (!empty($p['PaymentID'])) {
                $details[] = $p;
            }
        }

        return $details;
    }

    private function savePayments(
        array $payments,
        int $parentId,
        bool $isBill,
        string $currencyCode,
        float $docRate = 1.0
    ): void {
        foreach ($payments as $p) {
            $paymentId = $p['PaymentID'];
            $accountCode = data_get($p, 'Account.Code');

            if (!$accountCode) {
                Log::warning("[XeroWebhook] payment {$paymentId}: AccountCode kosong, dilewati.");
                $this->syncIncomplete = true;
                continue;
            }

            $bank = BankXero::where('code', $accountCode)->first();
            if (!$bank) {
                Log::warning("[XeroWebhook] payment {$paymentId}: kode bank '{$accountCode}' tidak ditemukan.");
                $this->syncIncomplete = true;
                continue;
            }

            $currency = strtoupper($currencyCode ?: 'IDR');
            $amount = (float) ($p['Amount'] ?? 0);

            // Rate payment dipakai jika ada, kalau tidak pakai rate dokumen
            $rate = (float) ($p['CurrencyRate'] ?? 0);
            if ($rate <= 0) {
                $rate = $docRate;
            }

            $nominalFx = $this->fxToIdr($currency, $rate);          // IDR per 1 unit currency
            $totalBase = (int) round($amount * $nominalFx, 0);      // nominal dalam IDR

            TransactionNominalBankAccount::updateOrCreate(
                ['payment_uuid' => $paymentId],
                [
                    'uuid_bank' => $bank->id,
                    'nominal_receive' => $isBill ? 0 : $amount,
                    'nominal_spend' => $isBill ? $amount : 0,
                    'nominal_transfer' => 0,
                    'total_base_receive' => $isBill ? 0 : $totalBase,
                    'total_base_spend' => $isBill ? $totalBase : 0,
                    'nominal_currency' => $nominalFx,
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

    /**
     * Cari ItemsPaketAllFromXero berdasarkan code dari response Xero.
     * Kalau belum ada, buat baru. Return id-nya (untuk d_bills.item_id_prod_serv).
     */
    private function resolveBillItemMaster(array $line, array &$cache): ?int
    {
        $code = trim((string) ($line['ItemCode'] ?? data_get($line, 'Item.Code') ?? ''));
        if ($code === '') {
            return null;
        }

        $key = mb_strtolower($code);
        if (isset($cache[$key])) {
            return $cache[$key];
        }

        $item = ItemsPaketAllFromXero::where('code', $code)->first();

        if (!$item) {
            $item = ItemsPaketAllFromXero::create([
                'code' => $code,
                'nama_paket' => data_get($line, 'Item.Name')
                    ?: ($line['Description'] ?? $code),
                'uuid_proudct_and_service' => data_get($line, 'Item.ItemID')
                    ?: (string) \Illuminate\Support\Str::uuid(),
                'tax_rate_salles' => 0,   // NOT NULL tanpa default
                'tax_rate_purchase' => 0,   // NOT NULL tanpa default
            ]);
        }

        return $cache[$key] = (int) $item->id;
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
            $this->syncIncomplete = true;
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