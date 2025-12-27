<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;//
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\PaymentsHistoryFix;
use Illuminate\Support\Facades\DB;
use App\ConfigRefreshXero;
class PaymentHistoryController extends Controller
{
    //

    use ConfigRefreshXero;


    private function parseXeroDate($xeroDate) {
        if (preg_match('/\/Date\((\d+)([+-]\d+)?\)\//', $xeroDate, $matches)) {
            $timestamp = $matches[1] / 1000;
            return date('Y-m-d', $timestamp);
        }
        return date('Y-m-d');
    }

    public function getHistoryInvoice($invoice_id)
    {
        $getData = PaymentsHistoryFix::select('invoice_number','invoice_uuid','date','amount')->where('invoice_uuid',$invoice_id)->orderBy('date','asc')->get();
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

            Log::info("Cron Job History: Mulai sinkronisasi...");

            // 2. Loop per Halaman (Batch Processing)
            do {
                // Filter langsung dari Xero: Hanya ambil yang PAID agar ringan
                $response = Http::withHeaders($headers)
                    ->get('https://api.xero.com/api.xro/2.0/Invoices', [
                        'page' => $page,
                        'where' => 'Status=="PAID"'
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
                                        'reference'      => $payment["Reference"]
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
                }

                // Pindah ke halaman berikutnya
                $page++;

                // 4. Rate Limiting Safety
                // Xero max 60 req/menit. Sleep 1 detik per page aman.
                sleep(1);

            } while ($hasMoreData);

            Log::info("Cron Job History: Selesai.");
            return response()->json(['status' => 'success', 'message' => 'Sync Selesai']);

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
