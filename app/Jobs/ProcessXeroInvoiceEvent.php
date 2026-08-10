<?php

namespace App\Jobs;

use App\Http\Repository\MasterData\DataJamaahXeroRepository;
use App\Models\MasterData\DataJamaahXero;
use App\Models\Transaction\VaTransUser;
use App\Models\MasterData\Coa;
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

// TODO: sesuaikan namespace 5 model ini dengan lokasi asli di project kamu
use App\Models\InvoicesAllFromXero;
use App\Models\MasterData\ItemDetailInvoices;

use App\Models\ItemsPaketAllFromXero;
use App\Models\Transaction\TransactionAllCoa;

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

            $this->syncVaTransFromLineItems($invoice, $invoiceNumber, $repo_contact);

            // insert/update detail invoice + line items + posting COA SAVE to db
            $this->processInvoice($invoice);

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
    // FULL INVOICE + LINE ITEM SYNC (dipindah dari SyncXeroInvoiceJob)
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

    private function processInvoice(array $inv): void
    {
        $lineItems = $inv['LineItems'] ?? [];
        $firstLine = $lineItems[0] ?? [];
        $issueDate = $this->parseXeroDate($inv['DateString'] ?? $inv['Date'] ?? null);
        $dueDate = $this->parseXeroDate($inv['DueDateString'] ?? $inv['DueDate'] ?? null);
        $contactId = data_get($inv, 'Contact.ContactID');

        $findContact = DataJamaahXero::where('uuid_contact', $contactId)->value('id') ?? 1;

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

        if (!$parentId || empty($lineItems)) {
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
                'uuid_detail_inv' => (string) Str::uuid(), // TODO: ganti balik ke $service_global->generateUniqueString() kalau ada aturan format khusus
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