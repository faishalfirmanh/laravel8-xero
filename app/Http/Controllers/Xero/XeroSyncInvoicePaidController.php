<?php

namespace App\Http\Controllers\Xero;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use App\ConfigRefreshXero;
use App\Models\InvoicesAllFromXero;
use App\Models\ItemsPaketAllFromXero;
use Illuminate\Support\Str;
use App\Services\GlobalService;
use App\Services\XeroRateLimitService;
use Illuminate\Support\Facades\DB;

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

            if (empty($invoices)) return;

            $data = $invoices[0]; // INI DATA HEADER INVOICE

            // Pastikan ada LineItems
            $lineItems = $data['LineItems'] ?? [];

            foreach ($lineItems as $lineItem) {

                InvoicesAllFromXero::updateOrCreate(
                    [
                        // KUNCI PENCARIAN
                        'invoice_uuid' => $data['InvoiceID'], // Ambil dari $data (Header)
                        //'uuid_proudct_and_service' => $lineItem['LineItemID'] // Ambil dari $lineItem (Detail)
                    ],
                    [
                        // --- DATA HEADER (Ambil dari variabel $data) ---
                        'invoice_uuid'   => $data['InvoiceID'],
                        'invoice_amount' => $data['AmountPaid'],
                        'invoice_number' => $data['InvoiceNumber'],
                        'invoice_total'  => $data['Total'], // Biasanya 'Total', bukan 'SubTotal' utk total akhir
                        'issue_date'     => $data['DateString'],
                        'due_date'       => $data['DueDateString'],
                        'status'         => $data['Status'],
                        'uuid_contact'   => $data['Contact']['ContactID'],
                        'contact_name'   => $data['Contact']['Name'],

                        // --- DATA DETAIL ITEM (Ambil dari variabel $lineItem) ---
                        'uuid_proudct_and_service' => $lineItem['LineItemID'],
                        'item_name'      => $lineItem['Description'] ?? ($lineItem['Item']['Name'] ?? ''),
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

        $page  = max((int) $request->get('page', 1), 1);
        $limit = 5;
        $offset = ($page - 1) * $limit;

        $keyword = strtoupper(trim($request->get('keyword', '')));

        $query = InvoicesAllFromXero::query()
        ->select([
            'invoice_uuid',
            DB::raw('UPPER(invoice_number) as invoice_number'),
            'invoice_number',
            'invoice_amount',
        ]);

        if ($keyword !== '') {
            $query->whereRaw('UPPER(no_invoice) LIKE ?', ['%' . $keyword . '%']);
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
                'data' => $data,
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'has_more' => ($offset + $limit) < $total
            ]
        ], 200);
    }

     public function getAllPaketLocal(Request $request)
    {

        $page  = max((int) $request->get('page', 1), 1);
        $limit = 5;
        $offset = ($page - 1) * $limit;

        $query = ItemsPaketAllFromXero::query()
        ->select([
            'uuid_proudct_and_service',
            'nama_paket',
            'total_hari',
            'created_at'
        ])
        ->orderBy('created_at', 'desc');

        $total = $query->count();
        $data = $query
            ->offset($offset)
            ->limit($limit)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'data' => $data,
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'has_more' => ($offset + $limit) < $total
            ]
        ], 200);
    }

    public function getInvoicePaidArrival()
    {
        //set_time_limit(100); // 5 Menit agar tidak timeout

        $tokenData = $this->getValidToken();
        if (!$tokenData) {
            return response()->json(['message' => 'Token kosong/invalid.'], 401);
        }

        //dd(111);
        $page = 1;
        $tenantId = $this->getTenantId($tokenData['access_token']);

        $totalSyncedInvoiceLines = 0; // Menghitung baris item, bukan jumlah invoice header
        $isFinished = false;

        // --- BAGIAN 1: SYNC INVOICE ---
        $aa = Http::withHeaders([
                        'Authorization' => 'Bearer ' . $tokenData['access_token'],
                        'Xero-Tenant-Id' => $tenantId,
                        'Accept' => 'application/json',
                    ])

                    ->get('https://api.xero.com/api.xro/2.0/Invoices');
        dd($aa);
        try {
            while (!$isFinished) {
                $this->rateLimiter->checkAndHit($tenantId);

                // 1. Request List Invoice (Sudah termasuk LineItems)
                // Gunakan Retry Wrapper agar aman
                $response = $this->xeroRequestWithRetry(function() use ($tokenData, $tenantId, $page) {
                     Http::withHeaders([
                        'Authorization' => 'Bearer ' . $tokenData['access_token'],
                        'Xero-Tenant-Id' => $tenantId,
                        'Accept' => 'application/json',
                    ])
                    ->timeout(60)
                    ->get('https://api.xero.com/api.xro/2.0/Invoices', [
                        'order' => 'Date DESC',
                        'page' => $page
                    ]);
                });
                //dd($response);

                if ($response->serverError()) {
                    Log::error('Xero 500 Error Page ' . $page);
                    return response()->json(['message' => 'Xero Server Error'], 503);
                }

                $data_invoice_all = $response->json('Invoices') ?? [];

                if (empty($data_invoice_all)) {
                    $isFinished = true;
                    break;
                }

                // 2. Loop Data
                foreach ($data_invoice_all as $invoice) {
                    if (empty($invoice['InvoiceNumber'])) continue;

                    // Ambil LineItems langsung dari response utama (TIDAK PERLU REQUEST ULANG)
                    $lineItems = $invoice['LineItems'] ?? [];

                    foreach ($lineItems as $lineItem) {

                        InvoicesAllFromXero::updateOrCreate(
                            [
                                'invoice_uuid' => $invoice['InvoiceID'],
                                'uuid_proudct_and_service' => $lineItem['LineItemID'] // KUNCI KEDUA AGAR TIDAK TIMPA DATA
                            ],
                            [
                                // Data Header Invoice
                                'invoice_uuid'   => $invoice['InvoiceID'],
                                'invoice_amount' => $invoice['AmountPaid'],
                                'invoice_number' => $invoice['InvoiceNumber'],
                                'invoice_total'  => $invoice['SubTotal'],
                                'issue_date'     => $invoice['DateString'],
                                'due_date'       => $invoice['DueDateString'],
                                'status'         => $invoice['Status'],
                                'uuid_contact'   => $invoice['Contact']['ContactID'],
                                'contact_name'   => $invoice['Contact']['Name'],

                                // Data Detail Item
                                'uuid_proudct_and_service' => $lineItem['LineItemID'],
                                'item_name'      => $lineItem['Description'] ?? ($lineItem['Item']['Name'] ?? ''),
                                'item_code'      => $lineItem['ItemCode'] ?? null, // Simpan juga kodenya
                            ]
                        );

                        $totalSyncedInvoiceLines++;
                    }
                }

                // Cek Pagination
                if (count($data_invoice_all) < 100) {
                    $isFinished = true;
                } else {
                    $page++;
                    sleep(2); // Jeda wajib 2 detik antar halaman
                }
            }

            // Jeda sebelum pindah ke Items
            sleep(2);

            // --- BAGIAN 2: SYNC ITEMS (PAKET) ---
            $page_item = 1;
            $totalSyncedItem = 0;
            $isFinished_item = false;

            while (!$isFinished_item) {
                $this->rateLimiter->checkAndHit($tenantId);

                $resItem = $this->xeroRequestWithRetry(function() use ($tokenData, $tenantId, $page_item) {
                    return Http::withHeaders([
                        'Authorization' => 'Bearer ' . $tokenData['access_token'],
                        'Xero-Tenant-Id' => $tenantId,
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ])
                    ->timeout(30)
                    ->get('https://api.xero.com/api.xro/2.0/Items', [
                        'order' => 'Code ASC', // Lebih rapi pakai Code
                        'page' => $page_item
                    ]);
                });

                $itemPaketAndProduct = $resItem->json('Items') ?? [];

                if (empty($itemPaketAndProduct)) {
                    $isFinished_item = true;
                    break;
                }

                foreach ($itemPaketAndProduct as $value) {
                    if (!isset($value['Name']) || trim($value['Name']) === '') continue;
                    if (substr_count($value['Name'], '/') !== 2) continue;

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

                if (count($itemPaketAndProduct) < 100) {
                    $isFinished_item = true;
                } else {
                    $page_item++;
                    sleep(2); // Jeda wajib
                }
            }

            return response()->json([
                'status' => 'success',
                'pesan_invoice' => 'Total Baris Item Invoice tersimpan: ' . $totalSyncedInvoiceLines,
                'pesan_paket'   => 'Total Paket tersimpan: ' . $totalSyncedItem,
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
