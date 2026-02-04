<?php

namespace App\Http\Controllers\Xero;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use App\ConfigRefreshXero;
use App\Models\InvoicesAllFromXero;
use App\Models\MasterData\ItemDetailInvoices;
use App\Models\ItemsPaketAllFromXero;
use Illuminate\Support\Str;
use App\Services\GlobalService;
use App\Services\XeroRateLimitService;
use Illuminate\Support\Facades\DB;
use PhpParser\Node\Stmt\TryCatch;

class XeroSyncInvoicePaidController extends Controller
{
    protected $rateLimiter;
    use ConfigRefreshXero;
    protected $global;

    public function __construct(XeroRateLimitService $rateLimiter, GlobalService $global)
    {
        $this->rateLimiter = $rateLimiter;
        $this->global = $global;
    }

    public function syncSingleInvoice($invoiceId)
    {
        $tokenData = $this->getValidToken();
        $tenantId = env('XERO_TENANT_ID');

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $tokenData['access_token'],
            'Xero-Tenant-Id' => $tenantId,
            'Accept' => 'application/json',
        ])
            ->retry(3, 1000) // Coba 3x jika gagal
            ->get('https://api.xero.com/api.xro/2.0/Invoices/' . $invoiceId);

        if ($response->successful()) {
            $invoices = $response->json('Invoices');

            if (empty($invoices))
                return;

            $data = $invoices[0]; // INI DATA HEADER INVOICE

            // Pastikan ada LineItems
            $lineItems = $data['LineItems'] ?? [];

            foreach ($lineItems as $lineItem) {

                //dd($data);

                InvoicesAllFromXero::updateOrCreate(
                    [
                        // KUNCI PENCARIAN
                        'invoice_uuid' => $data['InvoiceID'], // Ambil dari $data (Header)
                        //'uuid_proudct_and_service' => $lineItem['LineItemID'] // Ambil dari $lineItem (Detail)
                    ],
                    [
                        // --- DATA HEADER (Ambil dari variabel $data) ---
                        'invoice_uuid' => $data['InvoiceID'],
                        'invoice_amount' => $data['AmountPaid'],
                        'invoice_number' => $data['InvoiceNumber'],
                        'invoice_total' => $data['Total'], // Biasanya 'Total', bukan 'SubTotal' utk total akhir
                        'issue_date' => $data['DateString'],
                        'due_date' => $data['DueDateString'],
                        'status' => $data['Status'],
                        'uuid_contact' => $data['Contact']['ContactID'],
                        'contact_name' => $data['Contact']['Name'],

                        // --- DATA DETAIL ITEM (Ambil dari variabel $lineItem) ---
                        'uuid_proudct_and_service' => $lineItem['LineItemID'],
                        'item_name' => $lineItem['Description'] ?? ($lineItem['Item']['Name'] ?? ''),
                        //'item_code'      => $lineItem['ItemCode'] ?? null,
                        // Tambahan jika perlu:
                        // 'quantity'    => $lineItem['Quantity'],
                        // 'unit_amount' => $lineItem['UnitAmount'],
                    ]
                );
            }

            Log::info("Sukses Sync Single Invoice: " . $data['InvoiceNumber']);
        } else {
            Log::error("Gagal Sync Single Invoice $invoiceId: " . $response->body());
        }
    }

    public function getAllInvoiceLocal(Request $request)
    {
        $page = max((int) $request->get('page', 1), 1);
        $limit = 10; // Naikkan sedikit limitnya biar UX lebih enak (misal 10 atau 20)
        $offset = ($page - 1) * $limit;

        // Ambil keyword dari parameter 'term' (default Select2) atau 'keyword'
        $keyword = strtoupper(trim($request->get('keyword', '')));
        //dd($keyword);
        $query = InvoicesAllFromXero::query()
            //->where('status','PAID')
            ->select([
                'status',
                'invoice_uuid', // Ini akan jadi ID
                'invoice_number', // Ini akan jadi Text yang tampil
                'invoice_amount',
                'contact_name',
                'due_date'
            ]);

        if ($keyword !== '') {
            // Pastikan nama kolom sesuai database (misal: invoice_number)
            $query->where(function ($q) use ($keyword) {
                $q->whereRaw('UPPER(invoice_number) LIKE ?', ['%' . $keyword . '%'])
                    ->orWhereRaw('UPPER(contact_name) LIKE ?', ['%' . $keyword . '%'])
                    ->orWhereRaw("UPPER(DATE_FORMAT(due_date, '%W, %e %M %Y')) LIKE ?", ['%' . $keyword . '%']);
            });
        }

        $totalQuery = clone $query; // Clone untuk hitung total sebelum limit
        $total = $totalQuery->count();

        $data = $query->orderBy('created_at', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();

        return response()->json([
            'status' => 'success',
            // Struktur ini disesuaikan agar mudah dibaca JS
            'results' => $data,
            'pagination' => [
                'more' => ($offset + $limit) < $total
            ]
        ], 200);
    }

    public function getDetaPaketByInvoice(Request $request)
    {
        $invoiceIds = $request->input('invoice_ids', []);
        $filter = explode(",", $invoiceIds);
        //dd($filter);
        $data = ItemDetailInvoices::whereIn('uuid_invoices', $filter)->get();
        return response()->json([
            'status' => 'success',
            'data' => $data
        ], 200);
    }

    public function getPaketByUuuidInvoice(Request $request)
    {
        $page = max((int) $request->get('page', 1), 1);
        $limit = 5;
        $offset = ($page - 1) * $limit;
        //$paket_uuid = $request->uuid_paket;
        $invoiceIds = $request->input('paket_uuid', []);
        $keyword = strtoupper(trim($request->get('keyword', '')));
        //dd($invoiceIds)
        //$list_uuid =  array_column($paket_uuid, 'id');
        $query = ItemsPaketAllFromXero::query()
            ->whereIn('uuid_proudct_and_service', $invoiceIds)
            ->select([
                'jenis_item',
                'uuid_proudct_and_service',
                'nama_paket',
                'total_hari',
                'created_at'
            ]);
        if ($keyword !== '') {
            // Pastikan nama kolom sesuai database (misal: invoice_number)
            $query->where(function ($q) use ($keyword) {
                $q->whereRaw('UPPER(nama_paket) LIKE ?', ['%' . $keyword . '%']);
            });
        }
        $query->orderBy('created_at', 'desc');
        $total = $query->count();
        $data = $query
            ->offset($offset)
            ->limit($limit)
            ->get();
        //dd($data);

        return response()->json([
            'status' => 'success',
            'data' => [
                'results' => $data,
                'pagination' => [
                    'more' => ($offset + $limit) < $total
                ]
            ]
        ], 200);
    }

    public function getAllPaketLocal(Request $request)
    {

        $page = max((int) $request->get('page', 1), 1);
        $limit = 5;
        $offset = ($page - 1) * $limit;
        $keyword = strtoupper(trim($request->get('keyword', '')));

        $query = ItemsPaketAllFromXero::query()
            ->where('jenis_item', 1)
            ->select([
                'jenis_item',
                'uuid_proudct_and_service',
                'nama_paket',
                'total_hari',
                'created_at'
            ]);

        if ($keyword !== '') {
            // Pastikan nama kolom sesuai database (misal: invoice_number)
            $query->where(function ($q) use ($keyword) {
                $q->whereRaw('UPPER(nama_paket) LIKE ?', ['%' . $keyword . '%']);
            });
        }

        $query->orderBy('created_at', 'desc');

        $total = $query->count();
        $data = $query
            ->offset($offset)
            ->limit($limit)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'results' => $data,
                'pagination' => [
                    'more' => ($offset + $limit) < $total
                ]
                // 'page' => $page,
                // 'limit' => $limit,
                // 'total' => $total,
                // 'has_more' => ($offset + $limit) < $total
            ]
        ], 200);
    }


    //bisa tapi lama, sync semua item  dan invoice detail
    public function getInvoicePaidArrivalLama()
    {
        //set_time_limit(600); // Naikkan jadi 10 menit jika data ribuan

        ini_set('max_execution_time', 0);
        set_time_limit(0);

        $tokenData = $this->getValidToken();
        if (!$tokenData) {
            return response()->json(['message' => 'Token kosong/invalid.'], 401);
        }

        $page = 1;
        $tenantId = $this->getTenantId($tokenData['access_token']);

        $totalSyncedInvoiceLines = 0;
        $totalSyncedItem = 0;
        $isFinished = false;

        try {
            // --- BAGIAN 1: SYNC INVOICE ---
           // $start_time = Carbon::now()->format('d-m-Y H.i');
            Log::info("(/admin/list-invoice) mulai sync invoice ");
            while (!$isFinished) {
                $this->rateLimiter->checkAndHit($tenantId);

                $response = $this->xeroRequestWithRetry(function () use ($tokenData, $tenantId, $page) {
                    return Http::withHeaders([
                        'Authorization' => 'Bearer ' . $tokenData['access_token'],
                        'Xero-Tenant-Id' => $tenantId,
                        'Accept' => 'application/json',
                    ])
                        ->timeout(15) // Naikkan timeout sedikit untuk jaga-jaga
                        ->get('https://api.xero.com/api.xro/2.0/Invoices', [
                            'where' => 'Status=="PAID"',
                            'order' => 'Date DESC',
                            'page' => $page
                        ]);
                });

                if ($response->serverError()) {
                    Log::error('Xero 500 Error Page ' . $page);
                    break;
                }

                // Hitung Limit
                $min_rem = (int) $response->header('X-MinLimit-Remaining');
                $day_rem = (int) $response->header('X-DayLimit-Remaining');
                $this->global->requestCalculationXero($min_rem, $day_rem);

                $data_invoice_all = $response->json('Invoices') ?? [];

                if (count($data_invoice_all) < 1) {
                    $isFinished = true;
                    break;
                }

                // --- OPTIMASI DIMULAI DI SINI ---
                // Kita tampung dulu datanya ke Array (RAM), jangan langsung ke DB.
                $batchInvoices = [];
                $batchLineItems = [];

                foreach ($data_invoice_all as $invoice) {
                    if (empty($invoice['InvoiceNumber']))
                        continue;

                    // 1. Tampung Header Invoice
                    $batchInvoices[] = [
                        'invoice_uuid' => $invoice['InvoiceID'],
                        'invoice_amount' => $invoice['AmountPaid'],
                        'invoice_number' => $invoice['InvoiceNumber'],
                        'invoice_total' => $invoice['SubTotal'],
                        'issue_date' => $invoice['DateString'], // Pastikan format tanggal sesuai kolom DB
                        'due_date' => $invoice['DueDateString'] ?? null,
                        'status' => $invoice['Status'],
                        'uuid_contact' => $invoice['Contact']['ContactID'],
                        'contact_name' => $invoice['Contact']['Name'],
                        // Tambahkan created_at/updated_at manual jika perlu
                        'updated_at' => now(),
                    ];

                    // 2. Tampung Line Items
                    $lineItems = $invoice['LineItems'] ?? [];
                    foreach ($lineItems as $lineItem) {
                        if (isset($lineItem["Item"])) {
                            // Pastikan LineItemID ada, jika null (jarang terjadi) buat fallback
                            $lineItemId = $lineItem['LineItemID'] ?? $invoice['InvoiceID'] . '-' . $lineItem["Item"]["ItemID"];
                            if (substr_count($lineItem["Item"]["Name"], '/') !== 2) continue;

                            $batchLineItems[] = [
                                'line_item_uuid' => $lineItemId, // Pastikan kolom ini ada di DB & Unique
                                'uuid_invoices' => $invoice['InvoiceID'],
                                'uuid_item' => $lineItem["Item"]["ItemID"],
                                'qty' => $lineItem['Quantity'],
                                'unit_price' => $lineItem['UnitAmount'],
                                'total_amount_each_row' => $lineItem['LineAmount'],
                                'invoice_number' => $invoice['InvoiceNumber'],
                                'updated_at' => now(),
                            ];
                            $totalSyncedInvoiceLines++;
                        }
                    }
                }

                // EKSEKUSI DATABASE SEKALI JALAN (BULK UPSERT)
                DB::beginTransaction();
                try {
                    // Upsert Header
                    // Param 2: Kolom Unique Key (untuk mendeteksi update vs insert)
                    // Param 3: Kolom yang mau di-update jika data sudah ada
                    if (!empty($batchInvoices)) {
                        InvoicesAllFromXero::upsert($batchInvoices, ['invoice_uuid'], [
                            // 'invoice_amount',//biar tidak terlalu berat
                            // 'invoice_total',
                            // 'status',
                            // 'issue_date',
                            // 'due_date',
                            'updated_at'
                        ]);
                    }

                    // Upsert Line Items
                    if (!empty($batchLineItems)) {
                        ItemDetailInvoices::upsert($batchLineItems, ['line_item_uuid'], [
                            'qty',
                            'unit_price',
                            'total_amount_each_row',
                            'updated_at'
                        ]);
                    }

                    DB::commit();
                } catch (\Exception $e) {
                    DB::rollBack();
                    throw $e;
                }

                // Pagination Logic
                if (count($data_invoice_all) < 100) {
                    $isFinished = true;
                } else {
                    $page++;
                    // Smart Sleep: Jika sisa request banyak (>10), gas terus (sleep 0.2s).
                    // Jika sisa dikit (<10), baru sleep lama (2s).
                    $sleepTime = ($min_rem > 10) ? 200000 : 2000000;
                    usleep($sleepTime);
                }
            }

            // --- BAGIAN 2: SYNC ITEMS (PAKET) ---
            $page_item = 1;
            $isFinished_item = false;

            while (!$isFinished_item) {
                $this->rateLimiter->checkAndHit($tenantId);

                $resItem = $this->xeroRequestWithRetry(function () use ($tokenData, $tenantId, $page_item) {
                    return Http::withHeaders([
                        'Authorization' => 'Bearer ' . $tokenData['access_token'],
                        'Xero-Tenant-Id' => $tenantId,
                        'Accept' => 'application/json',
                    ])
                        ->timeout(15)
                        ->get('https://api.xero.com/api.xro/2.0/Items', [
                            'order' => 'Code ASC',
                            'page' => $page_item
                        ]);
                });

                $min_rem = (int) $resItem->header('X-MinLimit-Remaining');
                $day_rem = (int) $resItem->header('X-DayLimit-Remaining');
                $this->global->requestCalculationXero($min_rem, $day_rem);

                $itemPaketAndProduct = $resItem->json('Items') ?? [];

                if (empty($itemPaketAndProduct)) {
                    $isFinished_item = true;
                    break;
                }

                // BATCH ARRAY ITEM
                $batchItems = [];
                foreach ($itemPaketAndProduct as $value) {
                    if (!isset($value['Name']) || trim($value['Name']) === '')
                        continue;

                    $hari = self::getTotalHari($value['Name']) ?? 0;

                    $batchItems[] = [
                        'uuid_proudct_and_service' => $value['ItemID'],
                        'code' => $value['Code'] ?? null,
                        'nama_paket' => $value['Name'],
                        'purchase_AccountCode' => data_get($value, 'PurchaseDetails.AccountCode', '-'),
                        'sales_AccountCode' => data_get($value, 'SalesDetails.AccountCode', '-'),
                        'total_hari' => $hari,
                        'jenis_item' => $this->global->cekJenisPaketBasePagar($value['Name']),
                        'price_purchase' => data_get($value, 'PurchaseDetails.UnitPrice', 0),
                        'price_sales' => data_get($value, 'SalesDetails.UnitPrice', 0),
                        'desc' => $value['Description'] ?? '',
                        'updated_at' => now(),
                    ];
                    $totalSyncedItem++;
                }

                // EKSEKUSI DB ITEM
                if (!empty($batchItems)) {
                    ItemsPaketAllFromXero::upsert($batchItems, ['uuid_proudct_and_service'], [
                        // 'code', //biar tidak terlalu berat
                        // 'nama_paket',
                        // 'price_purchase',
                        // 'price_sales',
                        // 'desc',
                        'updated_at'
                    ]);
                }

                if (count($itemPaketAndProduct) < 100) {
                    $isFinished_item = true;
                } else {
                    $page_item++;
                    $sleepTime = ($min_rem > 10) ? 200000 : 2000000;
                    usleep($sleepTime);
                }
            }

            $view_req = $this->global->getDataAvailabeRequestXero();
            $end_time_sync = Carbon::now()->format('d-m-Y H.i');
            Log::info("(/admin/list-invoice) selesai sync invoice ".$end_time_sync);
            return response()->json([
                'status' => 'success',
                'pesan_invoice' => 'Total Baris Item Invoice tersimpan: ' . $totalSyncedInvoiceLines,
                'pesan_paket' => 'Total Paket tersimpan: ' . $totalSyncedItem,
                'request_min_tersisa_hari' => $view_req->available_request_day,
                'request_min_tersisa_menit' => $view_req->available_request_min,
            ]);

        } catch (\Exception $e) {
            Log::error("Sync Error: " . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'type' => 'process_error',
            ], 500);
        }
    }

    public function deletedDataLocal()
    {
        DB::beginTransaction();
        try {
            $hapus_detail = ItemDetailInvoices::query()->delete();
            $hapus_invoice = InvoicesAllFromXero::query()->delete();
            $hapus_paket = ItemsPaketAllFromXero::query()->delete();
            DB::commit();
            Log::info("deleted all invoice success: ");
           return response()->json([
                'status' => 'success',
                'message'=>"berhasil hapus invoice"
            ]);
        } catch (\Throwable $th) {
           DB::rollback();
            Log::error("deleted all invoice Error: " . $th->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => $th->getMessage(),
                'type' => 'process_error',
            ], 500);
        }

    }

    //hanya sync invoice
     public function getInvoicePaidArrival(Request $request)
    {
        // Hindari timeout PHP
        ini_set('max_execution_time', 0);
        set_time_limit(0);

        $tokenData = $this->getValidToken();
        if (!$tokenData) {
            return response()->json(['message' => 'Token kosong/invalid.'], 401);
        }

        $tenantId = $this->getTenantId($tokenData['access_token']);

        // --- OPTIMASI 1: FILTER TANGGAL (INCREMENTAL SYNC) ---
        // Default: Ambil data 30 hari terakhir saja.
        // Jika ingin semua, kirim parameter ?force_all=true
        $startDate = now()->subDays(30);
        if ($request->has('force_all') && $request->force_all == 'true') {
            $whereClause = 'Status=="PAID"'; // Hati-hati, ini berat!
            Log::warning("Melakukan Full Sync Invoice Xero");
        } else {
            // Format Xero untuk Date filtering: DateTime(YYYY,MM,DD)
            $dateStr = $startDate->format('Y,m,d');
            $whereClause = 'Status=="PAID" AND Date >= DateTime(' . $dateStr . ')';
            Log::info("Melakukan Partial Sync Invoice Xero mulai dari: " . $startDate->toDateString());
        }

        $page = 1;
        $totalSyncedInvoiceLines = 0;
        $totalSyncedItem = 0;
        $isFinished = false;

        try {
            // --- BAGIAN 1: SYNC INVOICE ---
            while (!$isFinished) {
                $this->rateLimiter->checkAndHit($tenantId);

                $response = $this->xeroRequestWithRetry(function () use ($tokenData, $tenantId, $page, $whereClause) {
                    return Http::withHeaders([
                        'Authorization' => 'Bearer ' . $tokenData['access_token'],
                        'Xero-Tenant-Id' => $tenantId,
                        'Accept' => 'application/json',
                    ])
                    ->timeout(20)
                    ->get('https://api.xero.com/api.xro/2.0/Invoices', [
                        'where' => $whereClause, // Filter tanggal diterapkan disini
                        'order' => 'Date DESC',
                        'page' => $page,
                        'unitdp' => 4 // Optimasi desimal presisi
                    ]);
                });

                if ($response->serverError()) {
                    Log::error('Xero 500 Error Page ' . $page);
                    break;
                }

                // Rate Limit Handling yang lebih aman
                $min_rem = (int) $response->header('X-MinLimit-Remaining');
                $day_rem = (int) $response->header('X-DayLimit-Remaining');
                $this->global->requestCalculationXero($min_rem, $day_rem);

                $data_invoice_all = $response->json('Invoices') ?? [];

                // Jika kosong, selesai
                if (count($data_invoice_all) == 0) {
                    $isFinished = true;
                    break;
                }

                $batchInvoices = [];
                $batchLineItems = [];

                foreach ($data_invoice_all as $invoice) {
                    if (empty($invoice['InvoiceNumber'])) continue;

                    // Tampung Header
                    $batchInvoices[] = [
                        'invoice_uuid' => $invoice['InvoiceID'],
                        'invoice_amount' => $invoice['AmountPaid'],
                        'invoice_number' => $invoice['InvoiceNumber'],
                        'invoice_total' => $invoice['SubTotal'],
                        'issue_date' => $invoice['DateString'],
                        'due_date' => $invoice['DueDateString'] ?? null,
                        'status' => $invoice['Status'],
                        'uuid_contact' => $invoice['Contact']['ContactID'],
                        'contact_name' => $invoice['Contact']['Name'],
                        'updated_at' => now(),
                    ];

                    // Tampung Line Items
                    if (isset($invoice['LineItems'])) {
                        foreach ($invoice['LineItems'] as $lineItem) {
                            if (isset($lineItem["Item"])) {
                                // Validasi nama item sederhana
                                if (!isset($lineItem["Item"]["Name"]) || substr_count($lineItem["Item"]["Name"], '/') !== 2) continue;

                                $lineItemId = $lineItem['LineItemID'] ?? $invoice['InvoiceID'] . '-' . $lineItem["Item"]["ItemID"];

                                $batchLineItems[] = [
                                    'line_item_uuid' => $lineItemId,
                                    'uuid_invoices' => $invoice['InvoiceID'],
                                    'uuid_item' => $lineItem["Item"]["ItemID"],
                                    'qty' => $lineItem['Quantity'],
                                    'unit_price' => $lineItem['UnitAmount'],
                                    'total_amount_each_row' => $lineItem['LineAmount'],
                                    'invoice_number' => $invoice['InvoiceNumber'],
                                    'updated_at' => now(),
                                ];
                                $totalSyncedInvoiceLines++;
                            }
                        }
                    }
                }

                DB::transaction(function () use ($batchInvoices, $batchLineItems) {
                    if (!empty($batchInvoices)) {
                        InvoicesAllFromXero::upsert($batchInvoices, ['invoice_uuid'], ['updated_at']); // Update updated_at saja kalau ada
                    }
                    if (!empty($batchLineItems)) {
                        ItemDetailInvoices::upsert($batchLineItems, ['line_item_uuid'], ['qty', 'unit_price', 'total_amount_each_row', 'updated_at']);
                    }
                });

                // Pagination Logic
                // Xero Max page size standard is 100 via API, tapi default paging mereka juga 100.
                if (count($data_invoice_all) < 100) {
                    $isFinished = true;
                } else {
                    $page++;
                    // Smart Sleep: Mencegah 429
                    // Jika sisa > 10, sleep 0.5 detik. Jika kritis, sleep 2 detik.
                    $sleepTime = ($min_rem > 10) ? 500000 : 2000000;
                    usleep($sleepTime);
                }
            }

            $view_req = $this->global->getDataAvailabeRequestXero();

            return response()->json([
                // 'status' => 'success',
                // 'pesan' => 'Sync Invoice (Filter: '. ($request->force_all ? 'ALL' : 'Last 30 Days') .') selesai.',
                // 'total_lines' => $totalSyncedInvoiceLines,
                // 'request_sisa' => $view_req->available_request_min,
                 'status' => 'success',
                'pesan_invoice' => 'Total Baris Item Invoice tersimpan: ' . $totalSyncedInvoiceLines,
                'pesan_paket' => 'Total Paket tersimpan: ',
                'request_min_tersisa_hari' => $view_req->available_request_day,
                'request_min_tersisa_menit' => $view_req->available_request_min,
            ]);

        } catch (\Exception $e) {
            Log::error("Sync Error: " . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    function cekFormatStringPaket($text) {
        if (substr($text, -2) === '#1') {
            return true;
        }
        if (substr_count($text, '/') === 2) {
            return true;
        }
        return false;
    }

    public function getPaketHajiUmroh(Request $request)
    {
         $tokenData = $this->getValidToken();
        if (!$tokenData) {
            return response()->json(['message' => 'Token kosong/invalid.'], 401);
        }

        $tenantId = $this->getTenantId($tokenData['access_token']);
        $page_item = 1;
        $resItem = $this->xeroRequestWithRetry(function () use ($tokenData, $tenantId, $page_item) {
                return Http::withHeaders([
                    'Authorization' => 'Bearer ' . $tokenData['access_token'],
                    'Xero-Tenant-Id' => $tenantId,
                    'Accept' => 'application/json',
                ])
                    ->timeout(15)
                    ->get('https://api.xero.com/api.xro/2.0/Items', [
                        'order' => 'Code ASC',
                        'page' => $page_item
                    ]);
            });
        $min_rem = (int) $resItem->header('X-MinLimit-Remaining');
                $day_rem = (int) $resItem->header('X-DayLimit-Remaining');
                $this->global->requestCalculationXero($min_rem, $day_rem);

        $itemPaketAndProduct = $resItem->json('Items') ?? [];
        $totalSyncedItem = 0;
        $batchItems = [];
        foreach ($itemPaketAndProduct as $value) {
            if (!isset($value['Name']) || trim($value['Name']) === '')
                continue;

            if(self::cekFormatStringPaket($value["Name"])){
                $hari = self::getTotalHari($value['Name']) ?? 0;
                $batchItems[] = [
                    'uuid_proudct_and_service' => $value['ItemID'],
                    'code' => $value['Code'] ?? null,
                    'nama_paket' => $value['Name'],
                    'purchase_AccountCode' => data_get($value, 'PurchaseDetails.AccountCode', '-'),
                    'sales_AccountCode' => data_get($value, 'SalesDetails.AccountCode', '-'),
                    'total_hari' => $hari,
                    'jenis_item' => $this->global->cekJenisPaketBasePagar($value['Name']),
                    'price_purchase' => data_get($value, 'PurchaseDetails.UnitPrice', 0),
                    'price_sales' => data_get($value, 'SalesDetails.UnitPrice', 0),
                    'desc' => $value['Description'] ?? '',
                    'updated_at' => now(),
                ];
                $totalSyncedItem++;
            }
        }

        if (!empty($batchItems)) {
            ItemsPaketAllFromXero::upsert($batchItems, ['uuid_proudct_and_service'], [
                'updated_at'
            ]);
        }
        $view_req = $this->global->getDataAvailabeRequestXero();
        $end_time_sync = Carbon::now()->format('d-m-Y H.i');
        Log::info("(/xero/sync-item-paket) selesai sync paket ".$end_time_sync);
        return response()->json([
            'status' => 'success',
            'pesan_paket' => 'Total Paket tersimpan: ' . $totalSyncedItem,
            'request_min_tersisa_hari' => $view_req->available_request_day,
            'request_min_tersisa_menit' => $view_req->available_request_min,
        ]);
    }


    private function xeroRequestWithRetry($callback, $maxRetries = 3)
    {
        $attempts = 0;

        do {
            $attempts++;
            $response = $callback();

            // Jika status 429 (Too Many Requests)
            if ($response->status() === 429) {
                // Ambil header Retry-After, jika tidak ada default 2 detik
                $retryAfter = (int) $response->header('Retry-After');
                $sleepSeconds = $retryAfter > 0 ? $retryAfter : 2;

                Log::warning("Xero 429 Hit. Sleeping for {$sleepSeconds} seconds. Attempt {$attempts}/{$maxRetries}");

                // Tidur sesuai permintaan Xero
                sleep($sleepSeconds);
                continue; // Coba loop lagi
            }

            // Jika sukses atau error lain (bukan 429), kembalikan response
            return $response;

        } while ($attempts < $maxRetries);

        throw new \Exception("Xero API Rate Limit Exceeded after {$maxRetries} attempts.");
    }

    function getTotalHari(string $text): ?int
    {
       if (preg_match('/(\d{1,3})\s*(?:hari|h)\b/i', $text, $matches)) {
            return (int) $matches[1];
        }
        return null;
    }

}
