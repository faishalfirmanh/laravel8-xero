<?php

namespace App\Jobs;

use App\Http\Repository\MasterData\DataJamaahXeroRepository;
use App\Models\MasterData\DataJamaahXero;
use App\Models\Transaction\VaTransUser;
use App\Models\Transaction\TransactionNominalBankAccount;
use App\Models\MasterData\Coa;
use App\Models\MasterData\BankXero;
use App\Services\XeroService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Str;

// TODO: sesuaikan namespace model-model ini dengan lokasi asli di project kamu
use App\Models\InvoicesAllFromXero;
use App\Models\MasterData\ItemDetailInvoices;
use App\Models\ItemsPaketAllFromXero;
use App\Models\Transaction\TransactionAllCoa;

// Model sisi Bill (ACCPAY) — dipakai kalau webhook INVOICE ternyata membawa
// Purchase Bill, bukan Sales Invoice. ADJUST namespace sesuai project kamu;
// ini mengikuti pola yang sama dipakai di SyncBillJob.
use App\Models\Expenses\Purchase\Bill\PBill;
use App\Models\Expenses\Purchase\Bill\DBill;

class ProcessXeroInvoiceEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected array $event;

    private array $trackingCache = [];

    public function __construct(array $event)
    {
        $this->event = $event;
    }

    public function handle(XeroService $xero, DataJamaahXeroRepository $repo_contact)
    {
        $invoiceId = $this->event['resourceId'] ?? null;
        $tenantId = $this->event['tenantId'] ?? null;

        if (!$invoiceId || !$tenantId) {
            return;
        }

        try {
            $invoice = $xero->getInvoice($tenantId, $invoiceId);

            // pengecekan: pastikan respons Xero valid sebelum diproses lebih lanjut
            if (empty($invoice) || empty($invoice['InvoiceID'])) {
                Log::warning("Invoice {$invoiceId}: respons Xero kosong/tidak valid, dilewati.");
                return;
            }

            $invoiceNumber = $invoice['InvoiceNumber'] ?? $invoiceId;
            $safeName = preg_replace('/[^A-Za-z0-9\-_]/', '', $invoiceNumber);
            $fileName = $safeName . '.json';

            Storage::disk('local')->put(
                'xero-invoices/' . $fileName,
                json_encode($invoice, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            );

            // ── PENTING ──────────────────────────────────────────────────────
            // Xero mengirim eventCategory "INVOICE" untuk DUA jenis objek:
            // Sales Invoice (Type=ACCREC) DAN Purchase Bill (Type=ACCPAY) —
            // keduanya sama-sama endpoint /Invoices di Xero API (quirk Xero,
            // bukan typo). SEBELUM fix ini, job selalu memperlakukan setiap
            // event sebagai Sales Invoice; kalau webhook-nya untuk Bill, data
            // akan salah masuk ke tabel AR (InvoicesAllFromXero) dan salah
            // tercatat sebagai pemasukan (is_speend=0), padahal itu pengeluaran.
            $type = $invoice['Type'] ?? null;

            if ($type === 'ACCPAY') {
                $this->processBillFromWebhook($xero, $tenantId, $invoice, $invoiceNumber);
            } else {
                $this->syncVaTransFromLineItems($invoice, $invoiceNumber, $repo_contact);

                // insert/update detail invoice + line items + posting COA + payment SAVE to db
                $this->processInvoice($xero, $tenantId, $invoice);
            }

            Log::info("Invoice {$invoiceNumber} (Type: " . ($type ?? 'ACCREC') . ") berhasil diupdate ke {$fileName}");
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

    protected function syncVaTransFromLineItems(array $invoice, string $invoiceNumber, DataJamaahXeroRepository $repo_contact): void
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

        Log::info('job kirim va ProcessXeroInvoiceEvent.php' . $contactName . " inv number " . $invoiceNumber);

        if ($contactName) {
            Log::info("Cek kontak untuk invoice {$invoiceNumber}, ContactID: " . ($invoice['Contact']['ContactID'] ?? 'null'));
            $cari_contact = $repo_contact->whereData(['uuid_contact' => $invoice['Contact']['ContactID']])->first();
            Log::info("whereData selesai, hasil: " . ($cari_contact ? "ID {$cari_contact->id}" : 'tidak ketemu'));

            $rand_number = random_int(1000, 9999);

            $baseName = self::ambilKataPertama($contactName);
            $generate_user = $baseName . $rand_number;

            $plain_password = $baseName;
            $pass_user = Hash::make($plain_password);

            $contactnya = [];

            if ($cari_contact == null) {
                $contactnya['uuid_contact'] = $invoice['Contact']['ContactID'];
                $contactnya['full_name'] = isset($contactName) ? $contactName : trim(($invoice['Contact']['FirstName'] ?? '') . ' ' . ($invoice['Contact']['LastName'] ?? ''));
                $contactnya['phone_number'] = $invoice['Contact']['Phones'][0]['PhoneNumber'] ?? '';
                $contactnya['username'] = $generate_user;
                $contactnya['pass'] = $pass_user;
                $user_name_msg = $generate_user;
                $user_pass_msg = $plain_password;
                $repo_contact->CreateOrUpdate($contactnya, null);
                Log::info("create contact sync invoice webhook " . $contactName);
            } else {
                if ($cari_contact->username == null) {
                    $contactnya['username'] = $generate_user;
                    $contactnya['pass'] = $pass_user;
                    $user_name_msg = $generate_user;
                    $user_pass_msg = $plain_password;
                    $repo_contact->CreateOrUpdate($contactnya, $cari_contact->id);
                    Log::info("update contact sync invoice webhook " . $contactName);
                }
            }
        }

        Log::info("Mulai loop LineItems, jumlah item: " . count($lineItems));

        foreach ($lineItems as $item) {
            $description = $item['Description'] ?? '';
            $lineAmount = (float) ($item['LineAmount'] ?? 0);

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

            if ($lineAmount < 0) {
                $totPayment += abs($lineAmount);
            }

            if ($lineAmount > 0) {
                $totNominal += $lineAmount;
            }
        }

        Log::info("Loop LineItems selesai, vaNumber: " . ($vaNumber ?? 'TIDAK ADA'));

        if ($vaNumber === null) {
            Log::info("Invoice {$invoiceNumber}: tidak ditemukan baris VA (invoice mungkin masih draft/belum lengkap), skip proses VaTransUser & notifikasi.");
            return;
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

        //mekari
        SendVaNotifMekariJob::dispatch($phone, $invoiceNumber, $vaNumber, $bankName, $paketName, $totPayment, $totNominal, $user, $pass);
        //fonte
        //SendVaNotificationJob::dispatch($phone, $invoiceNumber, $vaNumber, $bankName, $paketName, $totPayment, $totNominal, $user, $pass);
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
    // FULL INVOICE (ACCREC) + LINE ITEM SYNC
    // ================================================================

    private function parseXeroDate(?string $dateStr): ?string
    {
        if (!$dateStr) {
            return null;
        }

        if (strpos($dateStr, 'T') !== false || strpos($dateStr, '-') !== false) {
            try {
                return Carbon::parse($dateStr)->format('Y-m-d');
            } catch (\Exception $e) {
                Log::warning("[parseXeroDate] Gagal parse tanggal ISO: $dateStr");
                return null;
            }
        }

        if (preg_match('/\/Date\((\d+)/', $dateStr, $matches)) {
            return Carbon::createFromTimestampMs((int) $matches[1])->format('Y-m-d');
        }

        Log::warning("[parseXeroDate] Format tanggal tidak dikenali: $dateStr");
        return null;
    }

    private function processInvoice(XeroService $xero, string $tenantId, array $inv): void
    {
        $lineItems = $inv['LineItems'] ?? [];
        $firstLine = $lineItems[0] ?? [];
        $issueDate = $this->parseXeroDate($inv['DateString'] ?? $inv['Date'] ?? null);
        $dueDate = $this->parseXeroDate($inv['DueDateString'] ?? $inv['DueDate'] ?? null);
        $contactId = data_get($inv, 'Contact.ContactID');

        $phones = $inv['Contact']['Phones'] ?? [];
        $mobilePhone = collect($phones)->firstWhere('PhoneType', 'MOBILE');
        $phoneNumber = $mobilePhone['PhoneNumber'] ?? ($phones[0]['PhoneNumber'] ?? null);

        $addresses = $inv['Contact']['Addresses'] ?? [];
        $streetAddress = collect($addresses)->firstWhere('AddressType', 'STREET');
        $detailAddress = $streetAddress['AddressLine1'] ?? ($addresses[0]['AddressLine1'] ?? null);

        $findContact = DataJamaahXero::where('uuid_contact', $contactId)->value('id');
        if ($findContact == NULL) { // JIKA TIDAK ADA Create baru
            $createContactk = DataJamaahXero::create([
                'uuid_contact' => $inv['Contact']['ContactID'],
                'full_name' => $inv['Contact']['Name'],
                'phone_number' => $phoneNumber,
                'detail_address' => $detailAddress
            ]);
            $findContact = $createContactk->id;
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
                    'contact_id' => $findContact,
                    'uuid_proudct_and_service' => $firstLine['ItemID'] ?? null,
                    'item_name' => $firstLine['Description'] ?? null,
                    'reference' => $inv['Reference'] ?? null,
                    'updated_at' => now(),
                    'created_at' => now(),
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
                'updated_at',
            ]
        );

        $parentId = InvoicesAllFromXero::where('invoice_uuid', $inv['InvoiceID'])->value('id');

        if (!$parentId) {
            Log::warning("Invoice {$inv['InvoiceID']}: gagal ambil parentId setelah upsert InvoicesAllFromXero, batal lanjut.");
            return;
        }

        // ── BARU: sync payment invoice (uang MASUK) -> TransactionNominalBankAccount ──
        // Sebelumnya job ini TIDAK PERNAH menyentuh TransactionNominalBankAccount untuk
        // invoice — deteksi pembayaran cuma lewat parsing teks "VA" di line item (lihat
        // syncVaTransFromLineItems di atas, tetap dipertahankan apa adanya). Ini
        // menambah sync resmi dari field Payments milik invoice Xero, idempoten
        // (dicek payment_uuid dulu) — pola sama seperti SyncBillJob::getDetailPayment().
        $this->syncPaymentsFromXeroObject($xero, $tenantId, $inv, $parentId, false);

        if (empty($lineItems)) {
            return;
        }

        $accountCodes = collect($lineItems)->pluck('AccountCode')->filter()->unique()->values()->toArray();
        $itemCodes = collect($lineItems)
            ->filter(fn($l) => isset($l['Item']['Code']))
            ->map(fn($l) => $l['Item']['Code'])
            ->unique()->values()->toArray();

        $coaMap = Coa::whereIn('code', $accountCodes)->pluck('id', 'code')->toArray();
        $itemMap = ItemsPaketAllFromXero::whereIn('code', $itemCodes)->pluck('id', 'code')->toArray();

        $batchDetails = [];

        foreach ($lineItems as $line) {
            $paketUuid = null;
            $divisiUuid = null;

            foreach ($line['Tracking'] ?? [] as $track) {
                $categoryName = strtolower($track['Name'] ?? '');
                $optionName = $track['Option'] ?? '';

                if (strpos($categoryName, 'nama paket') !== false) {
                    $paketUuid = $this->resolveTrackingUuid('Nama Paket', $optionName);
                } elseif (strpos($categoryName, 'divisi') !== false) {
                    $divisiUuid = $this->resolveTrackingUuid('Divisi', $optionName);
                }
            }

            $coaId = isset($line['AccountCode']) ? ($coaMap[$line['AccountCode']] ?? null) : null;
            $itemCode = $line['Item']['Code'] ?? null;
            $itemIdSave = $itemCode ? ($itemMap[$itemCode] ?? null) : null;
            $uuidItem = $line['Item']['ItemID'] ?? $line['ItemID'] ?? 'no_set';

            $batchDetails[] = [
                'invoice_number' => $inv['InvoiceNumber'] ?? null,
                'uuid_invoices' => $inv['InvoiceID'],
                'uuid_item' => $uuidItem,
                'qty' => $line['Quantity'] ?? 0,
                'unit_price' => $line['UnitAmount'] ?? 0,
                'total_amount_each_row' => $line['LineAmount'] ?? 0,
                'line_item_uuid' => $line['LineItemID'],
                'coa_id' => $coaId,
                'parent_inv_id' => $parentId,
                'item_id' => $itemIdSave,
                'uuid_detail_inv' => (string) Str::uuid(),
                'paket_tracking_uuid' => $paketUuid,
                'divisi_travel_tracking_uuid' => $divisiUuid,
                'desc' => $line['Description'] ?? null,
                'updated_at' => now(),
                'created_at' => now(),
            ];
        }

        if (empty($batchDetails)) {
            return;
        }

        ItemDetailInvoices::upsert(
            $batchDetails,
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
                'paket_tracking_uuid',
                'divisi_travel_tracking_uuid',
                'desc',
                'updated_at',
                'uuid_detail_inv',
            ]
        );

        $status = $inv['Status'] ?? null;

        if ($status === 'AUTHORISED' || $status === 'PAID') {
            $lineItemUuids = collect($batchDetails)->pluck('line_item_uuid')->toArray();

            $savedDetails = ItemDetailInvoices::whereIn('line_item_uuid', $lineItemUuids)
                ->get()->keyBy('line_item_uuid');

            foreach ($batchDetails as $detail) {
                if (empty($detail['coa_id'])) {
                    continue;
                }

                $saved = $savedDetails[$detail['line_item_uuid']] ?? null;
                if (!$saved) {
                    continue;
                }

                TransactionAllCoa::firstOrCreate(
                    ['uuid_detail' => $saved->uuid_detail_inv],
                    [
                        'date_transaction' => $issueDate,
                        'uuid_coa' => $detail['coa_id'],
                        'reference' => $inv['Reference'] ?? '-',
                        'is_speend' => 0,
                        'nominal' => $saved->total_amount_each_row,
                        'uuid_detail' => $saved->uuid_detail_inv,
                    ]
                );
            }
        }
    }

    // ================================================================
    // BILL (ACCPAY) HANDLING — dipakai kalau webhook "INVOICE" ternyata
    // membawa Purchase Bill, bukan Sales Invoice. Logic ini sengaja
    // mengikuti SyncBillJob::processBills() supaya kedua jalur (webhook
    // realtime & cron polling) menghasilkan data yang konsisten.
    // ================================================================

    private function processBillFromWebhook(XeroService $xero, string $tenantId, array $inv, string $invoiceNumber): void
    {
        $lineItems = $inv['LineItems'] ?? [];
        $issueDate = $this->parseXeroDate($inv['DateString'] ?? $inv['Date'] ?? null);
        $dueDate = $this->parseXeroDate($inv['DueDateString'] ?? $inv['DueDate'] ?? null);
        $contactId = data_get($inv, 'Contact.ContactID');

        // Bill (ACCPAY): p_bills tidak punya kolom uuid_contact/contact_name
        // terpisah — default ke id=1 kalau kontak belum ada, SAMA seperti
        // SyncBillJob::processBills(). Sengaja TIDAK membuat kontak baru +
        // username/password di sini, karena itu cuma relevan untuk kontak
        // jamaah/pelanggan (sisi invoice), bukan vendor/supplier.
        $findContact = DataJamaahXero::where('uuid_contact', $contactId)->value('id') ?? 1;

        PBill::upsert(
            [
                [
                    'bills_uuid_xero' => $inv['InvoiceID'],
                    'uuid_from' => $findContact,
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
                    'currency' => $inv['CurrencyCode'] ?? null,
                    'created_by' => 1,
                    'updated_at' => now(),
                    'created_at' => now(),
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
            Log::warning("Bill {$invoiceNumber}: gagal ambil parentId setelah upsert PBill, batal lanjut.");
            return;
        }

        // sync payment bill (uang KELUAR) -> TransactionNominalBankAccount
        $this->syncPaymentsFromXeroObject($xero, $tenantId, $inv, $parentId, true);

        if (empty($lineItems)) {
            return;
        }

        $accountCodes = collect($lineItems)->pluck('AccountCode')->filter()->unique()->values()->toArray();
        $coaMap = Coa::whereIn('code', $accountCodes)->pluck('id', 'code')->toArray();

        $batchDetails = [];

        foreach ($lineItems as $line) {
            $paketUuid = null;
            $divisiUuid = null;

            foreach ($line['Tracking'] ?? [] as $track) {
                $categoryName = strtolower($track['Name'] ?? '');
                $optionName = $track['Option'] ?? '';

                if (strpos($categoryName, 'nama paket') !== false) {
                    $paketUuid = $this->resolveTrackingUuid('Nama Paket', $optionName);
                } elseif (strpos($categoryName, 'divisi') !== false) {
                    $divisiUuid = $this->resolveTrackingUuid('Divisi', $optionName);
                }
            }

            $coaId = isset($line['AccountCode']) ? ($coaMap[$line['AccountCode']] ?? null) : null;
            $itemCode = $line['ItemCode'] ?? data_get($line, 'Item.Code');

            // Pakai LineItemID Xero (unik & stabil) sebagai key upsert idempoten,
            // fallback ke UUID random kalau entah kenapa Xero tidak mengirimkannya.
            $uuidDetail = $line['LineItemID'] ?? (string) Str::uuid();

            $batchDetails[] = [
                'bills_parent_id' => $parentId,
                'item_code' => $itemCode,
                'desc' => $line['Description'] ?? null,
                'qty' => $line['Quantity'] ?? 0,
                'unit_price' => $line['UnitAmount'] ?? 0,
                'account_id_coa' => $coaId,
                // ADJUST: diisi TaxAmount per baris (nominal), bukan persentase.
                // Samakan dengan konvensi di SyncBillJob.
                'tax_rate' => $line['TaxAmount'] ?? 0,
                'paket_tracking_uuid' => $paketUuid,
                'divisi_travel_tracking_uuid' => $divisiUuid,
                'amount' => $line['LineAmount'] ?? 0,
                'uuid_detail' => $uuidDetail,
                'updated_at' => now(),
                'created_at' => now(),
            ];
        }

        if (empty($batchDetails)) {
            return;
        }

        DBill::upsert(
            $batchDetails,
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

        $status = $inv['Status'] ?? null;

        if ($status === 'AUTHORISED' || $status === 'PAID') {
            $detailUuids = collect($batchDetails)->pluck('uuid_detail')->toArray();

            $savedDetails = DBill::whereIn('uuid_detail', $detailUuids)
                ->get()
                ->keyBy('uuid_detail');

            foreach ($batchDetails as $detail) {
                if (empty($detail['account_id_coa'])) {
                    continue;
                }

                $saved = $savedDetails[$detail['uuid_detail']] ?? null;
                if (!$saved) {
                    continue;
                }

                TransactionAllCoa::firstOrCreate(
                    ['uuid_detail' => $saved->uuid_detail],
                    [
                        'date_transaction' => $issueDate,
                        'uuid_coa' => $detail['account_id_coa'],
                        'reference' => $inv['Reference'] ?? '-',
                        // Bill = pengeluaran/expense -> is_speend = 1.
                        'is_speend' => 1,
                        'nominal' => $saved->amount,
                        'uuid_detail' => $saved->uuid_detail,
                    ]
                );
            }
        }

        Log::info("Bill {$invoiceNumber} berhasil disync (PBill/DBill) via webhook.");
    }

    /**
     * p_bills.status numerik: 0=draft, 1=awaiting, 2=paid.
     * ADJUST kalau definisi "awaiting" beda di sistemmu.
     */
    private function mapBillStatus(?string $xeroStatus): int
    {
        switch ($xeroStatus) {
            case 'PAID':
                return 2;
            case 'SUBMITTED':
            case 'AUTHORISED':
                return 1;
            case 'DRAFT':
            case 'VOIDED':
            case 'DELETED':
            default:
                return 0;
        }
    }

    private function mapAmountsAre(?string $xeroLineAmountTypes): int
    {
        switch ($xeroLineAmountTypes) {
            case 'Inclusive':
                return 1;
            case 'NoTax':
                return 0;
            case 'Exclusive':
            default:
                return 2;
        }
    }

    // ================================================================
    // PAYMENT SYNC — dipakai invoice (ACCREC) maupun bill (ACCPAY)
    // ================================================================

    /**
     * Sync array Payments milik invoice/bill Xero ke TransactionNominalBankAccount.
     * Idempoten: skip kalau payment_uuid sudah pernah tersimpan.
     *
     * Array Payments yang menempel di objek Invoice/Bill hasil getInvoice() Xero
     * cuma ringkasan (PaymentID, Date, Amount, Reference) — tidak membawa data
     * Account/bank. Supaya dapat AccountCode, di sini dipanggil
     * $xero->getPayment($tenantId, $paymentId) untuk fetch detail payment penuh,
     * sama seperti pola getDetailPayment() di SyncBillJob.
     */
    private function syncPaymentsFromXeroObject(XeroService $xero, string $tenantId, array $xeroObject, int $parentId, bool $isBill): void
    {
        $payments = $xeroObject['Payments'] ?? [];

        if (empty($payments)) {
            return;
        }

        $label = $isBill ? 'Bill' : 'Invoice';

        foreach ($payments as $paymentRow) {
            $paymentId = $paymentRow['PaymentID'] ?? null;
            if (!$paymentId) {
                continue;
            }

            $alreadySynced = TransactionNominalBankAccount::where('payment_uuid', $paymentId)->exists();
            if ($alreadySynced) {
                continue;
            }

            try {
                $payment = $xero->getPayment($tenantId, $paymentId);
            } catch (\Throwable $e) {
                Log::warning("{$label} payment {$paymentId}: gagal fetch detail payment - " . $e->getMessage());
                continue;
            }

            if (empty($payment) || empty($payment['PaymentID'])) {
                Log::warning("{$label} payment {$paymentId}: respons Xero kosong/tidak valid, dilewati.");
                continue;
            }

            $amount = (float) ($payment['Amount'] ?? 0);
            $accountCode = data_get($payment, 'Account.Code');
            $bankName = data_get($payment, 'Account.Name');
            $date = $this->parseXeroDate($payment['Date'] ?? null);
            $refDetail = $payment['Reference'] ?? '-';

            if (!$accountCode) {
                Log::warning("{$label} payment {$paymentId}: AccountCode kosong, dilewati. Ref: {$refDetail}");
                continue;
            }

            $findBank = BankXero::where('code', $accountCode)->first();

            if (!$findBank) {
                Log::warning("{$label} payment {$paymentId}: kode akun bank '{$accountCode}' tidak ditemukan (nama: {$bankName}), dilewati.");
                continue;
            }

            TransactionNominalBankAccount::updateOrCreate(
                ['payment_uuid' => $paymentId],
                [
                    'uuid_bank' => $findBank->id,
                    'nominal_receive' => $isBill ? 0 : $amount,
                    'nominal_spend' => $isBill ? $amount : 0,
                    'nominal_transfer' => 0,
                    'created_by' => 1,
                    'date_transaction' => $date,
                    'reference_detail' => $refDetail,
                    // id_parent_bill dan id_parent_invoice adalah KOLOM TERPISAH di
                    // TransactionNominalBankAccount (lihat relasi getPbill()/getInv()
                    // di model) — isi salah satunya sesuai arah transaksi, sisanya null,
                    // supaya relasi Eloquent-nya nyantol ke tabel yang benar.
                    'id_parent_bill' => $isBill ? $parentId : null,
                    'id_parent_invoice' => $isBill ? null : $parentId,
                ]
            );

            Log::info("{$label} payment {$paymentId} disync ke TransactionNominalBankAccount (uuid_bank: {$findBank->id}).");

            usleep(200_000); // throttle ringan, samakan dgn SyncBillJob::THROTTLE_PAYMENT_US
        }
    }

    // ================================================================
    // TRACKING CATEGORY RESOLVER (dengan in-memory cache)
    // ================================================================

    private function resolveTrackingUuid(string $parentName, string $optionName): ?string
    {
        $cacheKey = $parentName . '::' . $optionName;

        if (array_key_exists($cacheKey, $this->trackingCache)) {
            return $this->trackingCache[$cacheKey];
        }

        $kategori = DB::table('tracking_categories')
            ->where('name_parent_category', $parentName)
            ->whereJsonContains('lines_category', ['item_name_category' => $optionName])
            ->first();

        if (!$kategori) {
            return $this->trackingCache[$cacheKey] = null;
        }

        $lines = collect(json_decode($kategori->lines_category, true));
        $item = $lines->firstWhere('item_name_category', $optionName);

        return $this->trackingCache[$cacheKey] = ($item['item_uuid_category'] ?? null);
    }
}