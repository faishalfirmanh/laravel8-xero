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


    public function getInvoicePaidArrival()
    {
        set_time_limit(600); // Naikkan jadi 10 menit jika data ribuan

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
                            'invoice_amount',
                            'invoice_total',
                            'status',
                            'issue_date',
                            'due_date',
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
                        'code',
                        'nama_paket',
                        'price_purchase',
                        'price_sales',
                        'desc',
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

    //old
    public function getInvoicePaidArrivalV1()
    {
        set_time_limit(300); // 5 Menit agar tidak timeout

        $tokenData = $this->getValidToken();
        if (!$tokenData) {
            return response()->json(['message' => 'Token kosong/invalid.'], 401);
        }

        //dd(111);
        $page = 1;
        $tenantId = $this->getTenantId($tokenData['access_token']);

        $totalSyncedInvoiceLines = 0; // Menghitung baris item, bukan jumlah invoice header
        $isFinished = false;


        try {
            while (!$isFinished) {
                $this->rateLimiter->checkAndHit($tenantId);

                // 1. Request List Invoice (Sudah termasuk LineItems)
                // Gunakan Retry Wrapper agar aman
                $response = $this->xeroRequestWithRetry(function () use ($tokenData, $tenantId, $page) {
                    return Http::withHeaders([
                        'Authorization' => 'Bearer ' . $tokenData['access_token'],
                        'Xero-Tenant-Id' => $tenantId,
                        'Accept' => 'application/json',
                    ])
                        ->timeout(6)
                        ->get('https://api.xero.com/api.xro/2.0/Invoices', [
                            'order' => 'Date DESC',
                            'page' => $page
                        ]);
                });
                //dd($response);

                if ($response->serverError()) {
                    Log::error('Xero 500 Error Page ' . $page);
                    break;
                    return response()->json(['message' => 'Xero Server Error'], 503);
                }
                $available_min_req = (int) $response->header('X-MinLimit-Remaining');
                $available_day_req = (int) $response->header('X-DayLimit-Remaining');
                $this->global->requestCalculationXero($available_min_req, $available_day_req);
                $data_invoice_all = $response->json('Invoices') ?? [];

                if (count($data_invoice_all) < 1) {
                    $isFinished = true;
                    break;
                }


                DB::beginTransaction();
                try {
                    // 2. Loop Data
                    foreach ($data_invoice_all as $invoice) {
                        if (empty($invoice['InvoiceNumber']))
                            continue;

                        InvoicesAllFromXero::updateOrCreate(
                            [
                                'invoice_uuid' => $invoice['InvoiceID'],
                                //'uuid_proudct_and_service' => $lineItem['LineItemID'] // KUNCI KEDUA AGAR TIDAK TIMPA DATA
                            ],
                            [
                                // Data Header Invoice
                                'invoice_uuid' => $invoice['InvoiceID'],
                                'invoice_amount' => $invoice['AmountPaid'],
                                'invoice_number' => $invoice['InvoiceNumber'],
                                'invoice_total' => $invoice['SubTotal'],
                                'issue_date' => $invoice['DateString'],
                                'due_date' => isset($invoice['DueDateString']) ? $invoice['DueDateString'] : null,
                                'status' => $invoice['Status'],
                                'uuid_contact' => $invoice['Contact']['ContactID'],
                                'contact_name' => $invoice['Contact']['Name'],

                                // Data Detail Item
                                // 'uuid_proudct_and_service' => $lineItem['LineItemID'],
                                // 'item_name'      => $lineItem['Description'] ?? ($lineItem['Item']['Name'] ?? ''),
                                // 'item_code'      => $lineItem['ItemCode'] ?? null, // Simpan juga kodenya
                            ]
                        );


                        // Ambil LineItems langsung dari response utama (TIDAK PERLU REQUEST ULANG)
                        $lineItems = $invoice['LineItems'] ?? [];

                        foreach ($lineItems as $lineItem) {
                            if (isset($lineItem["Item"])) {
                                $item_uuid = $lineItem["Item"]["ItemID"];
                                //dd($lineItem);
                                $uniqueKey = isset($lineItem['LineItemID'])
                                    ? ['line_item_uuid' => $lineItem['LineItemID']] // Kunci paling aman
                                    : ['uuid_invoices' => $invoice['InvoiceID'], 'uuid_item' => $item_uuid];

                                ItemDetailInvoices::updateOrCreate(
                                    $uniqueKey,
                                    [
                                        'uuid_invoices' => $invoice['InvoiceID'],
                                        'uuid_item' => $item_uuid,
                                        'qty' => $lineItem['Quantity'],
                                        'unit_price' => $lineItem['UnitAmount'],
                                        'total_amount_each_row' => $lineItem['LineAmount'],
                                        'invoice_number' => $invoice['InvoiceNumber']
                                    ]
                                );
                                $totalSyncedInvoiceLines++;//invoices saja
                            }

                        }
                    }
                    DB::commit();
                } catch (\Exception $th) {
                    DB::rollBack();
                    throw $th;
                }

                // Cek Pagination
                if (count($data_invoice_all) < 100) {
                    $isFinished = true;
                } else {
                    $page++;
                    // sleep(2); // Jeda wajib 2 detik antar halaman
                    //usleep(500000);
                    usleep(200000);
                }
            }

            // Jeda sebelum pindah ke Items
            //sleep(2);

            // --- BAGIAN 2: SYNC ITEMS (PAKET) ---
            $page_item = 1;
            $totalSyncedItem = 0;
            $isFinished_item = false;

            while (!$isFinished_item) {
                $this->rateLimiter->checkAndHit($tenantId);

                $resItem = $this->xeroRequestWithRetry(function () use ($tokenData, $tenantId, $page_item) {
                    return Http::withHeaders([
                        'Authorization' => 'Bearer ' . $tokenData['access_token'],
                        'Xero-Tenant-Id' => $tenantId,
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ])
                        ->timeout(6)
                        ->get('https://api.xero.com/api.xro/2.0/Items', [
                            'order' => 'Code ASC', // Lebih rapi pakai Code
                            'page' => $page_item
                        ]);
                });

                $_min_req = (int) $resItem->header('X-MinLimit-Remaining');
                $_day_req = (int) $resItem->header('X-DayLimit-Remaining');

                $this->global->requestCalculationXero($_min_req, $_day_req);
                $itemPaketAndProduct = $resItem->json('Items') ?? [];

                if (empty($itemPaketAndProduct)) {
                    $isFinished_item = true;
                    break;
                }

                DB::beginTransaction();
                try {
                    foreach ($itemPaketAndProduct as $value) {
                        if (!isset($value['Name']) || trim($value['Name']) === '')
                            continue;
                        //if (substr_count($value['Name'], '/') !== 2) continue;//harus ada 2 //kusus paket induk, yang
                        //ada 2 slash (/)

                        $hari = self::getTotalHari($value['Name']) ?? 0;

                        ItemsPaketAllFromXero::updateOrCreate(
                            ['uuid_proudct_and_service' => $value['ItemID']],
                            [
                                'uuid_proudct_and_service' => $value['ItemID'],
                                'code' => $value['Code'] ?? null,
                                'nama_paket' => $value['Name'],
                                'purchase_AccountCode' => data_get($value, 'PurchaseDetails.AccountCode', '-'),
                                'sales_AccountCode' => data_get($value, 'SalesDetails.AccountCode', '-'),
                                'total_hari' => $hari,
                                'jenis_item' => $this->global->cekJenisPaketBasePagar($value['Name']),
                                'price_purchase' => data_get($value, 'PurchaseDetails.UnitPrice', 0),
                                'price_sales' => data_get($value, 'SalesDetails.UnitPrice', 0),
                                'desc' => $value['Description'] ?? ''
                            ]
                        );
                        $totalSyncedItem++;
                    }
                    DB::commit();
                } catch (\Throwable $th) {
                    DB::rollBack();
                    throw $th;
                }

                if (count($itemPaketAndProduct) < 100) {
                    $isFinished_item = true;
                } else {
                    $page_item++;
                    sleep(2); // Jeda wajib
                }
            }

            // $totalnya = $this->global->getDataAvailabeRequestXero();
            // dd($totalnya);
            //DB::commit();
            $view_req = $this->global->getDataAvailabeRequestXero();
            return response()->json([
                'status' => 'success',
                'pesan_invoice' => 'Total Baris Item Invoice tersimpan: ' . $totalSyncedInvoiceLines,
                'pesan_paket' => 'Total Paket tersimpan: ' . $totalSyncedItem,
                'request_min_tersisa_hari' => $view_req->available_request_day,
                'request_min_tersisa_menit' => $view_req->available_request_min,
            ]);

        } catch (\Exception $e) {
            //DB::rollback();
            Log::error("Sync Error: " . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'type' => 'process_error',
            ], 500);
        }
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
        if (preg_match('/(\d{1,3})\s*hari/i', $text, $matches)) {
            return (int) $matches[1];
        }
        return null;
    }

}
