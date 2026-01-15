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
use App\Services\XeroRateLimitService;
use Illuminate\Support\Facades\DB;

class XeroSyncInvoicePaidController extends Controller
{
    protected $rateLimiter;
    use ConfigRefreshXero;

    public function __construct(XeroRateLimitService $rateLimiter)
    {
        $this->rateLimiter = $rateLimiter;
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
        $tokenData = $this->getValidToken();
        if (!$tokenData) {
            return response()->json(['message' => 'Token kosong/invalid. Silakan akses /xero/connect dulu.'], 401);
        }

        $tenantId = $this->getTenantId($tokenData['access_token']);

        try {

            $this->rateLimiter->checkAndHit($tenantId);
            //$usage = $this->rateLimiter->getUsageInfo($tenantId);

            // 3. Request Data Contacts
            $response =
                // Http::withHeaders([
                //     'Authorization' => 'Bearer ' . $tokenData['access_token'],
                //     'Xero-Tenant-Id' => $tenantId,
                //     'Accept' => 'application/json',
                // ])
                //->timeout(30)
                // ->get('https://api.xero.com/api.xro/2.0/Invoices', [
                //     'where' => 'Status="PAID"',
                //     'order' => 'Date DESC'
                // ]);
                $this->xeroRequestWithRetry(function() use ($tokenData, $tenantId) {
            return Http::withHeaders([
                'Authorization' => 'Bearer ' . $tokenData['access_token'],
                'Xero-Tenant-Id' => $tenantId,
                'Accept' => 'application/json',
            ])
            ->timeout(30)
            ->get('https://api.xero.com/api.xro/2.0/Invoices', [
                'where' => 'Status="PAID"',
                'order' => 'Date DESC'
            ]);
        });




            $tot = 0;
            if ($response->serverError()) {
                Log::error('Xero API Internal Server Error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'headers' => $response->headers()
                ]);
                return response()->json([
                    'message' => 'Xero API sedang bermasalah, coba lagi nanti',
                    'status' => 503,
                ], 503);
            }
            // if (!$response->successful()) {
            //     return response()->json([
            //         'message' => 'Request gagal',
            //         'status' => $response->status(),
            //         'body' => $response->body()
            //     ], $response->status());
            // }
            $data_invoice_all =  $response->json('Invoices') ?? [];

            foreach ($response['Invoices'] as $key => $value) {
                if (empty($value['InvoiceNumber'])) {
                    continue;
                }
                $invoiceSaveDb =  InvoicesAllFromXero::firstOrCreate(
                        [
                            'invoice_uuid'   => $value['InvoiceID']
                        ],
                        [
                            'invoice_uuid'   => $value['InvoiceID'],
                            'invoice_amount' => $value['AmountPaid'],
                            'invoice_number' => $value['InvoiceNumber']
                        ]
                    );

                if($invoiceSaveDb->wasRecentlyCreated){
                    $tot++;
                }
            }

            sleep(1);
            $this->rateLimiter->checkAndHit($tenantId);
            $resItem = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $tokenData['access_token'],
                    'Xero-Tenant-Id' => $tenantId,//env('XERO_TENANT_ID'),
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
            ])->get('https://api.xero.com/api.xro/2.0/Items');
            $tot_paket = 0;

            $itemPaketAndProduct = $resItem->json('Items') ?? [];
            foreach ($itemPaketAndProduct as $key => $value) {
                if (!isset($value['Name']) || trim($value['Name']) === '') {
                    continue;
                }
                if (substr_count($value['Name'], '/') !== 2) {
                    continue;
                }
                $hari = self::getTotalHari($value['Name']) ?? 0;
                $paketSaveDb =  ItemsPaketAllFromXero::firstOrCreate(
                    [
                        'uuid_proudct_and_service'   => $value['ItemID']
                    ],
                    [
                        'uuid_proudct_and_service'   => $value['ItemID'],
                        'code'   => $value['Code'] ?? null,
                        'nama_paket' => $value['Name'],
                        'purchase_AccountCode' => data_get($value, 'PurchaseDetails.AccountCode', '-'),// $value["PurchaseDetails"]["AccountCode"] ?? '-',
                        'sales_AccountCode' => data_get($value, 'SalesDetails.AccountCode', '-'),//$value["SalesDetails"]["AccountCode"] ?? '-',
                        'total_hari'=> $hari

                    ]
                );


                if($paketSaveDb->wasRecentlyCreated){
                    $tot_paket++;
                }

            }

            return response()->json(
                [
                    'pesan_invoice'=> 'total data di singkronise invoice '.$tot,
                    'pesan_paket'=> 'total data di singkronise paket '.$tot_paket,
                ]
            );

        }catch (\Exception $e) {
            Log::error("rate limiter error sync paket dan invoice XeroSyncInvoicePaid line 40 ",
            ['usage'=>$this->rateLimiter->getUsageInfo($tenantId)]);
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'type' => 'rate_limit_exceeded',
                'usage' => $this->rateLimiter->getUsageInfo($tenantId)
            ], 429);
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
