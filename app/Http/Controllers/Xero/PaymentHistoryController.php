<?php

namespace App\Http\Controllers\Xero;

use Illuminate\Http\Request;//
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\PaymentsHistoryFix;
use Illuminate\Support\Facades\DB;
use App\Models\InvoicePriceGap;
use App\Services\GlobalService;
use App\ConfigRefreshXero;
class PaymentHistoryController extends Controller
{
    //

    private $xeroBaseUrl = 'https://api.xero.com/api.xro/2.0';
    use ConfigRefreshXero;

    protected $globalService;

    public function __construct(GlobalService $globalService)
    {
        $this->globalService = $globalService;
    }

    private function parseXeroDate($xeroDate) {
        if (preg_match('/\/Date\((\d+)([+-]\d+)?\)\//', $xeroDate, $matches)) {
            $timestamp = $matches[1] / 1000;
            return date('Y-m-d', $timestamp);
        }
        return date('Y-m-d');
    }

    public function getHistoryInvoice($invoice_id)
    {
        $getData = PaymentsHistoryFix::select('invoice_number','invoice_uuid','date','amount','reference','name_bank_transfer')->where('invoice_uuid',$invoice_id)->orderBy('date','asc')->get();
        return response()->json([
            'data'=>$getData
        ], 200);
    }

    function cekKataPertama($text) {
        $text = trim($text);
        $parts = explode(' ', $text, 2);
        $firstWord = $parts[0] ?? '';
        if (strtolower($firstWord) === '-man') {
            return true;
        }
        return false;
    }

    public function insertToHistory()
    {
        set_time_limit(0);
        ini_set('memory_limit', '512M');

        try {
            $tokenData = $this->getValidToken();
            if (!$tokenData) {
                Log::error('Cron Job History: Token Invalid');
                return response()->json(['message' => 'Token kosong/invalid.'], 401);
            }

            $tenantId = $this->getTenantId($tokenData['access_token']);
            $headers = [
                'Authorization' => 'Bearer ' . $tokenData['access_token'],
                'Xero-Tenant-Id' => $tenantId,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ];

            $page = 1;
            $hasMoreData = true;

            // --- OPTIMASI 1: Ambil data lebih banyak per request ---
            // Kita tidak request detail lagi di dalam loop.
            // Pastikan endpoint list ini mengembalikan field yang dibutuhkan:
            // Payments, Contact, InvoiceNumber, Total, AmountPaid

            Log::info("Cron Job History: Mulai sinkronisasi...");

            do {
                // Cek limit lokal sebelum nembak (Opsional, jika Anda simpan counter di DB)
                // $this->checkLocalLimit();

                $response = Http::withHeaders($headers)
                    ->get('https://api.xero.com/api.xro/2.0/Invoices', [
                        'page' => $page,
                        'where' => 'Status=="PAID" || Status=="AUTHORISED"',
                        // 'order' => 'Date DESC' // Opsional biar terurut
                    ]);

                // --- HANDLING ERROR 429 (Rate Limit) ---
                if ($response->status() == 429) {
                    $retryAfter = (int) $response->header('Retry-After');
                    // Jika header kosong, default 60 detik
                    $waitTime = $retryAfter > 0 ? $retryAfter : 65;

                    Log::warning("Kena Limit 429 Xero. Tidur selama $waitTime detik...");
                    sleep($waitTime);

                    // Jangan increment page, ulangi request ini
                    continue;
                }

                if ($response->failed()) {
                    Log::error("Cron Job Gagal di Page $page: " . $response->body());
                    break;
                }

                // Update Limit Info ke Service Global
                $av_min = (int) $response->header('X-MinLimit-Remaining');
                $av_day = (int) $response->header('X-DayLimit-Remaining');
                $this->globalService->requestCalculationXero($av_min, $av_day);

                // --- LOGIC SAFETY LIMIT ---
                // Jika limit menit tinggal dikit (misal < 5), tidur dulu sampai reset
                if ($av_min < 5) {
                    Log::info("Limit menit menipis ($av_min). Tidur 30 detik...");
                    sleep(30);
                }

                $invoices = $response->json()['Invoices'];

                if (empty($invoices)) {
                    $hasMoreData = false;
                    break;
                }

                // --- PROSES DATA (Tanpa Nembak API Detail Lagi) ---
                foreach ($invoices as $invoice) {

                    // --- 1. PROSES PAYMENT HISTORY ---
                    if (!empty($invoice['Payments'])) {
                        $contact_name = $invoice['Contact']['Name'] ?? "-";
                        $invoice_number = $invoice['InvoiceNumber'];
                        $invoice_id = $invoice['InvoiceID'];

                        foreach ($invoice['Payments'] as $payment) {
                            if (isset($payment["Reference"]) && $this->cekKataPertama($payment["Reference"])) {
                            $payment_uuid_nya =  $payment["PaymentID"];
                             $detail_payment = Http::withHeaders([
                                        'Authorization' => 'Bearer ' . $tokenData["access_token"],
                                        'Xero-Tenant-Id' => env("XERO_TENANT_ID"),
                                        'Content-Type' => 'application/json',
                                        'Accept' => 'application/json',
                                    ])->get("https://api.xero.com/api.xro/2.0/Payments/$payment_uuid_nya");
                               $account_bank = data_get($detail_payment->json(), 'Payments.0.Account.Name', 'no name bank');

                                DB::beginTransaction();
                                try {
                                    PaymentsHistoryFix::updateOrCreate(
                                        ['payment_uuid' => $payment["PaymentID"]],
                                        [
                                            'invoice_uuid'   => $invoice_id,
                                            'contact_name'   => $contact_name,
                                            'invoice_number' => $invoice_number,
                                            'date'           => $this->parseXeroDate($payment["Date"]),
                                            'amount'         => $payment["Amount"],
                                            'reference'      => $payment["Reference"],
                                            'payment_uuid' => $payment["PaymentID"],
                                            'name_bank_transfer' => $account_bank
                                        ]
                                    );
                                    DB::commit();
                                } catch (\Exception $e) {
                                    DB::rollBack();
                                    Log::error("Gagal save payment $invoice_number: " . $e->getMessage());
                                }
                            }
                        }
                    }

                    // --- 2. PROSES GAP / SELISIH (Menggunakan Data dari List) ---
                    // OPTIMASI: Data 'Total' dan 'AmountPaid' biasanya SUDAH ADA di list invoice
                    // Jadi tidak perlu nembak GET /Invoices/{id} lagi.

                    try {
                        // Pastikan field 'Total' ada. Jika Xero paging list tidak bawa field ini,
                        // barulah terpaksa nembak detail (tapi biasanya ada).
                        $total_xero = $invoice['Total'];

                        // Hitung lokal (ini query DB lokal, aman, cepat)
                        $local_total_payment = $this->globalService->getTotalLocalPaymentByuuidInvoice($invoice['InvoiceID']);

                        $hasil_selisih = $this->globalService->hitungSelisih($total_xero, $local_total_payment, 2);

                        $this->globalService->SavedInvoiceValue(
                            $invoice['InvoiceID'],
                            $invoice['InvoiceNumber'],
                            $invoice['Contact']['Name'] ?? 'null-name',
                            $total_xero,
                            $local_total_payment,
                            $hasil_selisih
                        );

                    } catch (\Throwable $th) {
                        Log::error("Gagal hitung gap inv " . $invoice['InvoiceNumber'] . ": " . $th->getMessage());
                    }
                }

                $page++;

                // Tidur sebentar antar halaman agar aman (opsional jika logic $av_min di atas sudah ada)
                // sleep(1);

            } while ($hasMoreData);

            $view_req =  $this->globalService->getDataAvailabeRequestXero();
            Log::info("Cron Job History: Selesai.");

            return response()->json([
                'status' => 'success',
                'message' => 'Sync Selesai',
                'request_min_tersisa_hari' => $view_req->available_request_day,
                'request_min_tersisa_menit'  => $view_req->available_request_min,
            ]);

        } catch (\Exception $e) {
            Log::error("Cron Job Error Global: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }


     public function insertToHistoryByuuidInvoice($invoiceId = null)
    {
        if (!$invoiceId) {
            $invoiceId = request('invoice_id');
        }

        if (!$invoiceId) {
            return response()->json(['message' => 'Invoice ID wajib diisi.'], 400);
        }

        set_time_limit(0); // Unlimited execution time (opsional untuk single request)
        ini_set('memory_limit', '512M');

        try {
            $tokenData = $this->getValidToken();
            if (!$tokenData) {
                Log::error('Sync Invoice: Token Invalid');
                return response()->json(['message' => 'Token kosong/invalid.'], 401);
            }

            $tenantId = $this->getTenantId($tokenData['access_token']);
            $headers = [
                'Authorization' => 'Bearer ' . $tokenData['access_token'],
                'Xero-Tenant-Id' => $tenantId,
                'Content-Type'   => 'application/json',
                'Accept'         => 'application/json',
            ];

            Log::info("Sync Invoice: Mulai sinkronisasi untuk ID $invoiceId...");

            $response = Http::withHeaders($headers)
                ->get("https://api.xero.com/api.xro/2.0/Invoices/$invoiceId");

            if ($response->status() == 429) {
                $retryAfter = (int) $response->header('Retry-After');
                $waitTime = $retryAfter > 0 ? $retryAfter : 65;
                Log::warning("Kena Limit 429 Xero saat sync invoice $invoiceId. Retry after $waitTime sec.");
                return response()->json(['message' => 'Rate Limit Reached', 'retry_after' => $waitTime], 429);
            }

            if ($response->failed()) {
                Log::error("Gagal Sync Invoice $invoiceId: " . $response->body());
                return response()->json(['message' => 'Gagal mengambil data dari Xero', 'error' => $response->body()], $response->status());
            }

            // --- UPDATE LIMIT INFO ---
            $av_min = (int) $response->header('X-MinLimit-Remaining');
            $av_day = (int) $response->header('X-DayLimit-Remaining');
            $this->globalService->requestCalculationXero($av_min, $av_day);

            // Ambil data Invoice (Array Invoices index 0 karena Xero selalu return array list walau single get)
            $invoicesData = $response->json()['Invoices'];

            if (empty($invoicesData)) {
                return response()->json(['message' => 'Invoice tidak ditemukan di Xero.'], 404);
            }

            $invoice = $invoicesData[0]; // Ambil elemen pertama

            // --- 1. PROSES PAYMENT HISTORY ---
            if (!empty($invoice['Payments'])) {
                $contact_name = $invoice['Contact']['Name'] ?? "-";
                $invoice_number = $invoice['InvoiceNumber'];
                $invoice_id_xero = $invoice['InvoiceID']; // Pastikan pakai ID dari response

                foreach ($invoice['Payments'] as $payment) {
                    // Filter referensi (logic Anda)
                    if (isset($payment["Reference"]) && $this->cekKataPertama($payment["Reference"])) {

                        // Detail payment perlu request lagi ke endpoint Payments?
                        // Jika data detail bank (Account Name) tidak ada di invoice response, maka request lagi.
                        // Request detail payment
                        $payment_uuid = $payment["PaymentID"];

                        // Cek limit safety sebelum request detail
                        if ($av_min < 5) {
                            sleep(2); // Sleep singkat saja
                        }

                        $detail_payment_res = Http::withHeaders($headers)
                            ->get("https://api.xero.com/api.xro/2.0/Payments/$payment_uuid");

                        // Update limit lagi dari response detail
                        if ($detail_payment_res->successful()) {
                            $av_min = (int) $detail_payment_res->header('X-MinLimit-Remaining');
                            $av_day = (int) $detail_payment_res->header('X-DayLimit-Remaining');
                            $this->globalService->requestCalculationXero($av_min, $av_day);
                        }

                        $account_bank = data_get($detail_payment_res->json(), 'Payments.0.Account.Name', 'no name bank');

                        DB::beginTransaction();
                        try {
                            PaymentsHistoryFix::updateOrCreate(
                                ['payment_uuid' => $payment["PaymentID"]],
                                [
                                    'invoice_uuid'       => $invoice_id_xero,
                                    'contact_name'       => $contact_name,
                                    'invoice_number'     => $invoice_number,
                                    'date'               => $this->parseXeroDate($payment["Date"]),
                                    'amount'             => $payment["Amount"],
                                    'reference'          => $payment["Reference"],
                                    'payment_uuid'       => $payment["PaymentID"],
                                    'name_bank_transfer' => $account_bank
                                ]
                            );
                            DB::commit();
                        } catch (\Exception $e) {
                            DB::rollBack();
                            Log::error("Gagal save payment $invoice_number ($payment_uuid): " . $e->getMessage());
                        }
                    }
                }
            }

            try {
                $total_xero = $invoice['Total'];
                $local_total_payment = $this->globalService->getTotalLocalPaymentByuuidInvoice($invoice['InvoiceID']);
                $hasil_selisih = $this->globalService->hitungSelisih($total_xero, $local_total_payment, 2);

                $this->globalService->SavedInvoiceValue(
                    $invoice['InvoiceID'],
                    $invoice['InvoiceNumber'],
                    $invoice['Contact']['Name'] ?? 'null-name',
                    $total_xero,
                    $local_total_payment,
                    $hasil_selisih
                );

            } catch (\Throwable $th) {
                Log::error("Gagal hitung gap inv " . $invoice['InvoiceNumber'] . ": " . $th->getMessage());
            }

            Log::info("Sync Invoice $invoiceId Selesai.");

            return response()->json([
                'status' => 'success',
                'message' => 'Sync Invoice Selesai',
                'invoice_number' => $invoice['InvoiceNumber'],
                'request_min_tersisa_hari' => $av_day,
                'request_min_tersisa_menit' => $av_min,
            ]);

        } catch (\Exception $e) {
            Log::error("Sync Invoice Error: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    //before 27-01-2026
    public function insertToHistoryV2()
    {
        // 1. Set Time Limit Unlimited untuk Cron Job
        set_time_limit(0);
        ini_set('memory_limit', '512M'); // Tambah memori jika perlu

        try {
            $tokenData = $this->getValidToken();

            if (!$tokenData) {
                Log::error('Cron Job History: Token Invalid');
                return response()->json(['message' => 'Token kosong/invalid.'], 401);
            }

            $tenantId = $this->getTenantId($tokenData['access_token']);

            // Header disiapkan sekali saja
            $headers = [
                'Authorization' => 'Bearer ' . $tokenData['access_token'],
                'Xero-Tenant-Id' => $tenantId,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ];

            $page = 1;
            $hasMoreData = true;
            //$start_time = Carbon::now()->format('d-m-Y H.i');
            Log::info("Cron Job History: Mulai sinkronisasi... ");

            // 2. Loop per Halaman (Batch Processing)
            do {
                // Filter langsung dari Xero: Hanya ambil yang PAID agar ringan
                $response = Http::withHeaders($headers)
                    ->get('https://api.xero.com/api.xro/2.0/Invoices', [
                        'page' => $page,
                        'where' =>'Status=="PAID" || Status=="AUTHORISED"', //'Status=="PAID"'
                    ]);

                if ($response->failed()) {
                    Log::error("Cron Job History Gagal di Page $page: " . $response->body());
                    // Jika error rate limit (429), break atau sleep lebih lama
                    if ($response->status() == 429) {
                        sleep(60); // Tunggu 1 menit lalu lanjut (opsional logic retry)
                        continue;
                    }
                    break; // Stop jika error fatal
                }

                $invoices = $response->json()['Invoices'];
                $available_min_req = (int) $response->header('X-MinLimit-Remaining');
                $available_day_req = (int) $response->header('X-DayLimit-Remaining');
                $this->globalService->requestCalculationXero($available_min_req, $available_day_req);
                  //return response()->json(['status' => $invoices, 'message' => 'Sync Selesai']);
                // Jika array kosong, berarti data habis
                if (empty($invoices)) {
                    $hasMoreData = false;
                    break;
                }

                // 3. Proses Data (Tanpa Nembak API Lagi)
                foreach ($invoices as $invoice) {
                    // Skip jika tidak ada data Payments
                    if (empty($invoice['Payments'])) {
                        continue;
                    }

                    $contact_name = $invoice['Contact']['Name'] ?? "-";
                    $invoice_number = $invoice['InvoiceNumber'];
                    $invoice_id = $invoice['InvoiceID'];

                    foreach ($invoice['Payments'] as $payment) {
                        // Cek Reference sesuai logic Anda
                        if (isset($payment["Reference"]) && $this->cekKataPertama($payment["Reference"])) {
                            // Gunakan Transaction per record atau per batch
                            DB::beginTransaction();
                            try {
                                PaymentsHistoryFix::updateOrCreate(
                                    [
                                        'payment_uuid' => $payment["PaymentID"],
                                    ],
                                    [
                                        'invoice_uuid'   => $invoice_id,
                                        'contact_name'   => $contact_name,
                                        'invoice_number' => $invoice_number,
                                        'date'           => $this->parseXeroDate($payment["Date"]),
                                        'amount'         => $payment["Amount"],
                                        'reference'      => $payment["Reference"],
                                        'payment_uuid' => $payment["PaymentID"],
                                    ]
                                );

                                DB::commit();
                                // Log level info mungkin terlalu bising untuk cron job jika data ribuan
                                Log::info("Sukses: insert history payment " . $invoice_number);

                            } catch (\Exception $e) {
                                DB::rollBack();
                                Log::error("Gagal save payment inv $invoice_number: " . $e->getMessage());
                            }
                        }
                    }

                    $responseDetails = Http::withHeaders($headers)->get($this->xeroBaseUrl . '/Invoices/' . $invoice_id);
                    if ($responseDetails->failed()) {
                        $statusCode = $responseDetails->status();
                        $errorBody  = $responseDetails->json();

                        Log::error('Xero Invoice Detail Error', [
                            'invoice_id' => $invoice_id,
                            'status'     => $statusCode,
                            'error'      => $errorBody
                        ]);
                        //Log::error("inv ".$invoice_id." Cron Job History Gagal koneksi pada detail : " . $responseDetails->body());
                        return response()->json(['status' => 'error', 'message' => 'Gagal koneksi ke Xero '.$responseDetails->status()], 500);
                    }
                    $invoiceData = $responseDetails->json()['Invoices'][0];
                    $local_total_payment = $this->globalService->getTotalLocalPaymentByuuidInvoice($invoice_id);
                    $total_xero = $invoiceData['Total'];//['AmountPaid'];

                    $av_min = (int) $responseDetails->header('X-MinLimit-Remaining');
                    $av_day = (int) $responseDetails->header('X-DayLimit-Remaining');
                    $this->globalService->requestCalculationXero($av_min, $av_day);
                    //dd($invoiceData);
                    $hasil_selisih = $this->globalService->hitungSelisih($total_xero, $local_total_payment, 2); //bcsub($total_xero, $local_total_payment, 2);
                    //DB::beginTransaction();
                    try {
                        //dd($invoiceData["Contact"]);
                        $this->globalService->SavedInvoiceValue($invoice_id,
                            $invoiceData["InvoiceNumber"],
                           isset($invoiceData["Contact"]) && $invoiceData["Contact"]["Name"] ? $invoiceData["Contact"]["Name"] : 'null-name',
                            $total_xero,
                            $local_total_payment,
                            $hasil_selisih
                        );
                        $view_req =  $this->globalService->getDataAvailabeRequestXero();
                       // DB::commit();
                        Log::info("Sukses: create or update tabel InvoicePriceGap PaymentHistoryController line : 170'
                          request_min_tersisa_hari' = $view_req->available_request_day ||
                         'request_min_tersisa_menit'  = $view_req->available_request_min");

                    } catch (\Throwable $th) {
                       // DB::rollBack();
                        Log::error("PaymentHistoryController line 173| gagal  create or update tabel InvoicePriceGap  ".$th->getMessage());
                    }
                }

                // Pindah ke halaman berikutnya
                $page++;

                // 4. Rate Limiting Safety
                // Xero max 60 req/menit. Sleep 1 detik per page aman.
                sleep(1);

            } while ($hasMoreData);

           // $end_time =  Carbon::now()->format('d-m-Y H.i');
            $view_req =  $this->globalService->getDataAvailabeRequestXero();
            Log::info("Cron Job History: Selesai. ");
            return response()->json(['status' => 'success',
                'message' => 'Sync Selesai',
                'request_min_tersisa_hari' => $view_req->available_request_day,
                'request_min_tersisa_menit'  => $view_req->available_request_min,
            ]);

        } catch (\Exception $e) {
            Log::error("Cron Job Error Global: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function insertToHistoryOld()
    {

        $tokenData = $this->getValidToken();

        if (!$tokenData) {
            return response()->json(['message' => 'Token kosong/invalid.'], 401);
        }

        $tenantId = $this->getTenantId($tokenData['access_token']);

        $invoicesResponse = Http::withHeaders([
            'Authorization' => 'Bearer ' . $tokenData['access_token'],
            'Xero-Tenant-Id' => $tenantId, //env('XERO_TENANT_ID'),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->get('https://api.xero.com/api.xro/2.0/Invoices');

        if ($invoicesResponse->failed()) {
            return response()->json(['error' => 'Gagal ambil invoices'], 500);
        }

        foreach ($invoicesResponse->json()['Invoices'] as $key => $value) {
            //
            $invoice_uid = $value["InvoiceID"];
            $invNumber = $value["InvoiceNumber"];

            $response_detail = Http::withHeaders([
                'Authorization' => 'Bearer ' . $tokenData["access_token"],
                'Xero-Tenant-Id' => env('XERO_TENANT_ID'),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->get("https://api.xero.com/api.xro/2.0/Invoices/$invoice_uid");

            $contact_name = $response_detail->json()['Invoices'][0]['Contact'] ? $response_detail->json()['Invoices'][0]['Contact']['Name'] : "-";
            $invoice_number = $response_detail->json()['Invoices'][0]['InvoiceNumber'];

            //dd($response_detail->json()['Invoices'][0]["Status"] == "PAID");
            if($response_detail->json()['Invoices'][0]["Status"] == "PAID")
            {
                if(isset($response_detail->json()['Invoices'][0]["Payments"]))
                {
                    if(count($response_detail->json()['Invoices'][0]["Payments"])>0){
                        $listPayment = $response_detail->json()['Invoices'][0]["Payments"];
                        foreach ($listPayment as $key_1 => $value_2) {
                            if(isset($value_2["Reference"]))
                            {
                                if($value_2["Reference"] == "-man"){
                                    DB::beginTransaction();
                                    try {
                                        PaymentsHistoryFix::updateOrCreate(
                                            [
                                                'payment_uuid' => $value_2["PaymentID"],
                                                //'invoice_uuid' => $value["InvoiceID"]
                                            ],
                                            [
                                                'invoice_uuid' => $value["InvoiceID"],
                                                'contact_name' => $contact_name,
                                                'invoice_number' => $invoice_number,
                                                'date' => $this->parseXeroDate($value_2["Date"]),
                                                'amount' =>   $value_2["Amount"],
                                                'reference' => $value_2["Reference"]
                                            ]
                                        );
                                        Log::info("sukses save payment history inv ".$invoice_number);
                                        DB::commit();
                                    } catch (\Exception $e) {
                                        Log::error("gagal insert payment history inv ".$invoice_number ." ". $e->getMessage() );
                                        DB::rollBack();
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}
