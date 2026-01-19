<?php

namespace App\Http\Controllers\Xero;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\PaymentParams;
use Illuminate\Support\Facades\Log;
use App\ConfigRefreshXero;
use App\Services\GlobalService;
class InvoicesDuplicateController extends Controller
{

     use ConfigRefreshXero;

     protected $globalService;

    public function __construct(GlobalService $globalService)
    {
        $this->globalService = $globalService;
    }

    function xeroDateToPhp($xeroDate, $format = 'Y-m-d') {
        if (empty($xeroDate)) return null;
        preg_match('/\/Date\((-?\d+)/', $xeroDate, $matches);
        if (!isset($matches[1])) return null;
        return date($format, $matches[1] / 1000);
    }


    function convertJsonDate($jsonDate) {
        if (preg_match('/\d+/', $jsonDate, $matches)) {
            // Ambil timestamp (milidetik)
            $milliSeconds = $matches[0];

            // Konversi ke detik
            $seconds = $milliSeconds / 1000;

            // Kembalikan format tanggal
            return date("Y-m-d", $seconds);
        }
        return null; // Jika format salah
    }

    public function updateInvoiceSelected(Request $request)
    {
        $rawContent = $request->getContent();
        $data = json_decode($rawContent, true);
        $array = [];
        $errors = [];

        //  dd($data['items']);
        foreach ($data['items'] as $value) {
            try {
                Log::info("=== MULAI PROSES INVOICE: " . $value['no_invoice'] . " ===");

                $hasPayment = ($value['no_payment'] != "kosong" && !empty($value['no_payment']));

                if ($hasPayment) {
                    Log::info("Status: Ada Payment (" . $value['no_payment'] . "). Melakukan Backup & Void.");
                    self::getDetailPayment($value['parentId']);
                    // self::updateInvoicePaidPerRows($value['no_payment']);
                    sleep(2);
                }

                // Update Item
                Log::info("Status: Memulai Update Invoice ke Xero...");
                self::updateInvoicePerRows($value['parentId'], $data['price_update'], $value['lineItemId']);

                if ($hasPayment) {
                    usleep(500000);
                    Log::info("Status: Restore Payment...");
                    //self::createPayments($value['parentId'], $value['no_payment']);
                    self::createPaymentsWithHistory($value['parentId']);
                    self::deletedRowInvoiceId($value['parentId']);
                }

                $array[] = ['no_invoice' => $value['no_invoice'], 'status' => 'Success'];
                Log::info("=== SELESAI SUCCESS ===");

            } catch (\Exception $e) {
                Log::info("InvoicesDuplicateController ,updateInvoiceSelected() line 74");
                Log::error("ERROR pada Invoice " . $value['no_invoice'] . ": " . $e->getMessage());
                $errors[] = ['no_invoice' => $value['no_invoice'], 'message' => $e->getMessage()];
            }
        }

        if (count($errors) > 0) return response()->json(['success' => $array, 'errors' => $errors], 207);
        return response()->json($array, 200);
    }

    public function createPaymentsWithHistory($invoice_idUnique)
    {
        $backup = PaymentParams::where('invoice_id', $invoice_idUnique)->get();

        // Cek isEmpty agar aman
        if ($backup->isEmpty()) return;

        $tokenData = $this->getValidToken();
        if (!$tokenData) {
            return response()->json(['message' => 'Token kosong/invalid. Silakan akses /xero/connect dulu.'], 401);
        }

        // Siapkan Header agar tidak berulang
        $headers = [
            'Authorization' => 'Bearer ' . $tokenData["access_token"],
            'Xero-Tenant-Id' => env("XERO_TENANT_ID"),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        // -----------------------------------------------------------
        // 1. GET INVOICE DATA (Ambil Sisa Hutang & Contact ID)
        // -----------------------------------------------------------
        try {
            $invResponse = Http::withHeaders($headers)
                ->get('https://api.xero.com/api.xro/2.0/Invoices/' . $invoice_idUnique);

            if ($invResponse->failed()) {
                throw new \Exception("Gagal mengambil detail Invoice: " . $invResponse->body());
            }

            $av_min = (int) $invResponse->header('X-MinLimit-Remaining');
            $av_day = (int) $invResponse->header('X-DayLimit-Remaining');
            $this->globalService->requestCalculationXero($av_min, $av_day);


            $xeroInvoice = $invResponse->json()['Invoices'][0];
            $currentAmountDue = (float) $xeroInvoice['AmountDue'];

            // PENTING: Kita butuh Contact ID untuk membuat Overpayment
            $contactID = $xeroInvoice['Contact']['ContactID'];

            Log::info("Sisa hutang invoice $invoice_idUnique: " . $currentAmountDue);

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            throw $e;
        }

        // -----------------------------------------------------------
        // 2. LOOP DATA PEMBAYARAN
        // -----------------------------------------------------------
        foreach ($backup as $key => $value) {

            $totalBayar = (float) $value->amount;
            $payDate = $value->date;
            $accId = $value->account_code;
            $ref = $value->reference;

            $bayarKeInvoice = 0;
            $bayarKeOverpayment = 0;

            // --- LOGIKA SPLIT PEMBAYARAN ---
            if ($currentAmountDue > 0) {
                if ($totalBayar <= $currentAmountDue) {
                    // Kasus A: Bayar pas atau kurang (Normal)
                    $bayarKeInvoice = $totalBayar;
                    $currentAmountDue -= $totalBayar; // Kurangi sisa hutang lokal
                } else {
                    // Kasus B: Bayar Lunas + Sisa Lebih (Overpayment)
                    $bayarKeInvoice = $currentAmountDue; // Bayar sisa hutang saja
                    $bayarKeOverpayment = $totalBayar - $currentAmountDue; // Sisanya simpan
                    $currentAmountDue = 0; // Hutang lunas
                }
            } else {
                // Kasus C: Hutang sudah 0, tapi user kirim uang lagi (Full Overpayment)
                $bayarKeOverpayment = $totalBayar;
            }

            // -------------------------------------------------------
            // EKSEKUSI A: POST PAYMENT (KE INVOICE)
            // -------------------------------------------------------
            if ($bayarKeInvoice > 0) {
                $resPay = Http::withHeaders($headers)
                    ->post('https://api.xero.com/api.xro/2.0/Payments', [
                        "Payments" => [[
                            "Invoice" => ["InvoiceID" => $invoice_idUnique],
                            "Account" => ["AccountID" => $accId],
                            "Date" => $payDate,
                            "Amount" => $bayarKeInvoice,
                            "Reference" => $ref
                        ]]
                    ]);

                if ($resPay->failed()) {
                    Log::error("Gagal Payment Invoice: " . $resPay->body());
                    throw new \Exception("Gagal Restore Payment: " . $resPay->body());
                } else {
                    Log::info("Sukses bayar Invoice: $bayarKeInvoice");
                }
            }

            // -------------------------------------------------------
            // EKSEKUSI B: PUT BANK TRANSACTION (OVERPAYMENT)
            // -------------------------------------------------------
            if ($bayarKeOverpayment > 0) {
                Log::warning("Membuat Overpayment sebesar: $bayarKeOverpayment");

                $resOver = Http::withHeaders($headers)
                    ->put('https://api.xero.com/api.xro/2.0/BankTransactions', [
                        "BankTransactions" => [[
                            "Type" => "RECEIVE-OVERPAYMENT", // Tipe khusus Overpayment
                            "Contact" => ["ContactID" => $contactID], // Wajib ada Contact
                            "BankAccount" => ["AccountID" => $accId], // Masuk ke Bank mana,//Harus account dengan type bank yang bisa
                            "Date" => $payDate,
                            "Reference" => $ref . " (Overpayment)",
                            "LineItems" => [[
                                "Description" => "Overpayment / Kelebihan bayar",
                                "UnitAmount" => $bayarKeOverpayment,
                                //"AccountCode" => "800", // Ganti kode akun ini jika perlu (misal akun suspens/liability)
                                // Atau hapus baris "AccountCode" agar Xero menggunakan default Accounts Receivable
                            ]]
                        ]]
                    ]);

                if ($resOver->failed()) {
                    Log::info("error InvoiceDuplicateController line 204");
                    Log::error("Gagal Overpayment: " . $resOver->body());
                    // Opsional: throw exception atau biarkan lanjut
                } else {
                    Log::info("Sukses catat Overpayment.");
                }
            }
        }
    }

    public function deletedRowInvoiceId($invId) {
        $find = PaymentParams::where('invoice_id', $invId)->delete();
        if($find){
            PaymentParams::where('invoice_id', $invId)->delete();
        }
    }

    public function ApiGetAccountCodePayments($paymentsId)
    {

        $tokenData = $this->getValidToken();
        if (!$tokenData) {
            return response()->json(['message' => 'Token kosong/invalid. Silakan akses /xero/connect dulu.'], 401);
        }
        $clean = trim($paymentsId, '"');
         $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $tokenData["access_token"],
            'Xero-Tenant-Id' => env("XERO_TENANT_ID"),
            'Accept' => 'application/json',
        ])->get("https://api.xero.com/api.xro/2.0/Payments/$clean");

        if ($response->failed()) throw new \Exception("Gagal Get Payment: " . $response->body());
        if (empty($response->json()['Payments'])) return;
        if ($response->json()["Payments"][0]["Status"] == 'DELETED') return;

        $account_id_payments = $response->json()["Payments"][0]["Account"]["AccountID"];
        return $account_id_payments;//$response->json()["Payments"][0]["Account"]["AccountID"];
    }

    public function getDetailPayment($codeInvoice) {
        // ... (Kode sama seperti sebelumnya) ...
        // Agar tidak kepanjangan, bagian ini aman jika data tersimpan di DB
        // dd($idInvoice);
        $tokenData = $this->getValidToken();
        if (!$tokenData) {
            return response()->json(['message' => 'Token kosong/invalid. Silakan akses /xero/connect dulu.'], 401);
        }

        $response_detail_invoice = Http::withHeaders([
            'Authorization' => 'Bearer ' . $tokenData["access_token"],
            'Xero-Tenant-Id' => env('XERO_TENANT_ID'),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->get("https://api.xero.com/api.xro/2.0/Invoices/$codeInvoice");

        $av_min = (int) $response_detail_invoice->header('X-MinLimit-Remaining');
        $av_day = (int) $response_detail_invoice->header('X-DayLimit-Remaining');
        $this->globalService->requestCalculationXero($av_min, $av_day);

        $history_payments = $response_detail_invoice->json()["Invoices"][0]["Payments"];
        foreach ($history_payments as $key_inv => $value_inv) {
           $final_date = $this->xeroDateToPhp($value_inv["Date"]);
           $account_code_api = self::ApiGetAccountCodePayments($value_inv["PaymentID"]);

            self::insertToDb(
                $value_inv["Amount"],
                $account_code_api,
                self::xeroDateToPhp($value_inv["Date"]),
                $codeInvoice,
                "Re-payment api updated BY system ".$value_inv["Reference"],
                $value_inv["PaymentID"]
            );
            self::updateInvoicePaidPerRows($value_inv["PaymentID"]);
        }

    }

    public function insertToDb($amount, $account_code, $date, $invoice_id, $reference_id, $idPayment) {
        PaymentParams::updateOrCreate(
            ['payments_id' => $idPayment],
            ['invoice_id' => $invoice_id,
             'account_code' => $account_code,
             'date' => $date,
             'amount' => $amount,
              'reference' => $reference_id]
        );
    }

    public function updateInvoicePaidPerRows($payment_id) {
        // ... (Kode sama) ...

        $tokenData = $this->getValidToken();
        if (!$tokenData) {
            return response()->json(['message' => 'Token kosong/invalid. Silakan akses /xero/connect dulu.'], 401);
        }
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $tokenData["access_token"],
            'Xero-Tenant-Id' => env("XERO_TENANT_ID"),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->post("https://api.xero.com/api.xro/2.0/Payments/$payment_id", ["Status" => "DELETED"]);

        $av_min = (int) $response->header('X-MinLimit-Remaining');
        $av_day = (int) $response->header('X-DayLimit-Remaining');
        $this->globalService->requestCalculationXero($av_min, $av_day);


        if ($response->failed() && $response->status() != 404) {
             throw new \Exception("Gagal Hapus Payment: " . $response->body());
        }
    }

    // --- BAGIAN KRUSIAL (DEBUGGING LOGIC) ---
    public function updateInvoicePerRows($parent_id, $amount_input, $line_item_id) {
        $cleanId = str_replace('"', '', $parent_id);

        // 1. Cek Status Invoice
        $maxRetries = 3;
        $attempt = 0;
        $isReady = false;
        $data = null;

        $tokenData = $this->getValidToken();
        if (!$tokenData) {
            return response()->json(['message' => 'Token kosong/invalid. Silakan akses /xero/connect dulu.'], 401);
        }


        while ($attempt < $maxRetries && !$isReady) {
            $res = Http::withHeaders([
                'Authorization' => 'Bearer ' . $tokenData["access_token"],
                'Xero-Tenant-Id' => env("XERO_TENANT_ID"),
                'Accept' => 'application/json',
            ])->get("https://api.xero.com/api.xro/2.0/Invoices/$cleanId");

            if ($res->successful()) {
                $data = $res->json();
                $status = $data['Invoices'][0]['Status'];
                Log::info("Try $attempt: Status Invoice saat ini: $status");
                $local_total_payment = $this->globalService->getTotalLocalPaymentByuuidInvoice($parent_id);
                $total_xero = $data['Invoices'][0]['Total'];//['AmountPaid'];
                $hasil_selisih = $this->globalService->hitungSelisih($total_xero, $local_total_payment, 2); //bcsub($total_xero, $local_total_payment, 2);
                //dd( $data["Invoices"][0]["Contact"]);
                $this->globalService->SavedInvoiceValue($cleanId,
                        $data["Invoices"][0]["InvoiceNumber"],
                        $data["Invoices"][0]["Contact"]["FirstName"] ?? $data["Invoices"][0]["Contact"]["Name"],
                        $total_xero,
                        $local_total_payment,
                        $hasil_selisih
                );

                if ($status == 'AUTHORISED' || $status == 'DRAFT') {
                    $isReady = true;
                } else {
                    sleep(1);
                }
            }
            $attempt++;
        }

        if (!$isReady) throw new \Exception("Gagal Buka Gembok Invoice. Status Masih: " . ($data['Invoices'][0]['Status'] ?? 'Unknown'));

        // 2. Susun Item Payload
        $itemsPayload = [];
        $found = false;

        Log::info("Mencari Target ID: " . $line_item_id);
        Log::info("Harga Baru yang diminta: " . $amount_input);

        foreach ($data['Invoices'] as $inv) {
            foreach ($inv['LineItems'] as $item) {

                // DEBUG: Log setiap item yang ada di invoice ini
                Log::info("Cek Item Xero line items : " . $item['LineItemID'] . " | Amount Asli: " . $item['UnitAmount']);

                // PERBANDINGAN ID
                if (strcasecmp(trim($item['LineItemID']), trim($line_item_id)) == 0) {
                    Log::info(">>> KETEMU! Update line itemID " . $item['LineItemID'] . " menjadi " . $amount_input);
                    $newAmount = $amount_input;
                    $found = true;
                } else {
                    $newAmount = $item['UnitAmount'];
                }

                $payload = [
                    'LineItemID' => $item['LineItemID'],
                    'Description' => $item['Description'], // Tidak ubah desc
                    'UnitAmount' => $newAmount,
                    'Quantity' => $item['Quantity'],
                ];

                // Masukkan field wajib lain
                if (isset($item['ItemCode'])) $payload['ItemCode'] = $item['ItemCode'];
                if (isset($item['AccountCode'])) $payload['AccountCode'] = $item['AccountCode'];
                if (isset($item['TaxType'])) $payload['TaxType'] = $item['TaxType'];

                $itemsPayload[] = $payload;
            }
        }

        if (!$found) {
            Log::error("CRITICAL: Target ID tidak ditemukan di list item Xero!");
            throw new \Exception("Item ID $line_item_id tidak ditemukan di Invoice $cleanId. Cek Log!");
        }

        // 3. Kirim Update
        $resUpdate = Http::withHeaders([
            'Authorization' => 'Bearer ' . $tokenData["access_token"],
            'Xero-Tenant-Id' => env("XERO_TENANT_ID"),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->post('https://api.xero.com/api.xro/2.0/Invoices', [
            'InvoiceID' => $parent_id,
            'LineItems' => $itemsPayload
        ]);

        if ($resUpdate->failed()) {
            Log::error("Gagal Update API: " . $resUpdate->body());
            throw new \Exception("Gagal Update Invoice: " . $resUpdate->body());
        }

        Log::info("Berhasil Update Invoice. Response Xero: " . $resUpdate->status());
    }

    public function createPayments($invoice_id, $old_payment_id) {
        //cek kode ini apakah bisa bayar lebih dari yang di bayarkan
        // ... (Kode sama, pastikan logika amountDue tetap ada) ...
        $backup = PaymentParams::where('payments_id', $old_payment_id)->first();
        if (!$backup) return;

        $tokenData = $this->getValidToken();
        if (!$tokenData) {
            return response()->json(['message' => 'Token kosong/invalid. Silakan akses /xero/connect dulu.'], 401);
        }

        $resInv = Http::withHeaders([
            'Authorization' => 'Bearer ' . $tokenData["access_token"],
            'Xero-Tenant-Id' => env("XERO_TENANT_ID"),
            'Accept' => 'application/json',
        ])->get("https://api.xero.com/api.xro/2.0/Invoices/$invoice_id");

        $payAmount = (float)$backup->amount;

        $totNya = 0;
        if ($resInv->successful()) {

           foreach ($resInv['Invoices'] as $key => $value) {
                foreach ($value["LineItems"] as $key2 => $value2) {
                  $totNya +=(float)  $value2["LineAmount"];
                }
           }
            $invData = $resInv->json();
            $due = (float)$invData['Invoices'][0]['AmountDue'];
            // dd($resInv);
            Log::info("Create Payment: Backup Amount: $payAmount | Tagihan Xero (Due): $due");

            if ($payAmount > $due) {
                Log::info("Penyesuaian: Nominal bayar diturunkan menjadi $due");
                $payAmount = $due;
            }
        }
        //dd($totNya);

        if ($payAmount <= 0) return;

        $resPay = Http::withHeaders([
            'Authorization' => 'Bearer ' . $tokenData["access_token"],
            'Xero-Tenant-Id' => env("XERO_TENANT_ID"),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->post('https://api.xero.com/api.xro/2.0/Payments', [
            "Payments" => [[
                "Invoice" => ["InvoiceID" => $invoice_id],
                // "Account" => ["Code" => $backup->account_code],
                "Account" => ["AccountID" => $backup->account_code],
                "Date" => $backup->date,
                "Amount" =>$totNya, //$payAmount,
                "Reference" => $backup->reference
            ]]
        ]);

        if ($resPay->failed()) throw new \Exception("Gagal Restore Payment: " . $resPay->body());
    }
}
