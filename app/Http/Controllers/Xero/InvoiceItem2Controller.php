<?php

namespace App\Http\Controllers\Xero;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\PaymentParams; // Pastikan model ini ada
use Carbon\Carbon;
use App\ConfigRefreshXero;
use App\Services\GlobalService;
class InvoiceItem2Controller extends Controller
{
    private $xeroBaseUrl = 'https://api.xero.com/api.xro/2.0';

    use ConfigRefreshXero;
    // Helper Headers

    protected $globalService;
    public function __construct(GlobalService $globalService)
    {
        $this->globalService = $globalService;
    }


    private function getHeaders()
    {
        $tokenData = $this->getValidToken();
        if (!$tokenData) {
            return response()->json(['message' => 'Token kosong/invalid. Silakan akses /xero/connect dulu.'], 401);
        }
        //dd($tokenData);
        return [
            'Authorization' => 'Bearer ' . $tokenData["access_token"],
            'Xero-Tenant-Id' => env("XERO_TENANT_ID"),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }

    /**
     * MAIN FUNCTION: SAVE ITEM (Add/Edit Row)
     * Menangani logika Paid Invoice secara otomatis.
     */
    public function saveItem(Request $request)
    {
        // 1. Validasi Input
        $request->validate([
            'invoice_id'    => 'required|string',
            'item_code'     => 'nullable|string',//nullable item_code
            'description'   => 'required|string',
            'qty'           => 'required|numeric',
            'price'         => 'required|numeric',
            'disc_amount'   => 'nullable|numeric',
            'agent_id'      => 'nullable|string',
            'divisi_id'     => 'nullable|string',
            'account_code'  => 'nullable|string',//nullable item_code
            // 'status_invoice' => 'required|string', // Tidak wajib, kita cek langsung dari API Xero
        ]);

        $invoiceId = preg_replace('/[^a-zA-Z0-9-]/', '', $request->invoice_id);

        try {
            // ------------------------------------------------------------------
            // LANGKAH 1: AMBIL DATA INVOICE & CEK STATUS PEMBAYARAN
            // ------------------------------------------------------------------
            $response = Http::withHeaders($this->getHeaders())->get($this->xeroBaseUrl . '/Invoices/' . $invoiceId);

            if ($response->failed()) {
                return response()->json(['status' => 'error', 'message' => 'Gagal koneksi ke Xero'], 500);
            }

            $available_min_req = (int) $response->header('X-MinLimit-Remaining');
            $available_day_req = (int) $response->header('X-DayLimit-Remaining');
            $this->globalService->requestCalculationXero($available_min_req, $available_day_req);

            $invoiceData = $response->json()['Invoices'][0];
            $currentLineItems = $invoiceData['LineItems'] ?? [];
            $payments = $invoiceData['Payments'] ?? [];
            $paymentBackups = [];

            // Jika ada pembayaran, lakukan Backup & Void (Delete) Payment
            //dd($payments);
            if (!empty($invoiceData['CreditNotes'])) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Gagal Update: Invoice ini sudah dipotong Credit Note. Mohon lakukan "Remove Allocation" manual di Xero terlebih dahulu sebelum edit via sistem.'
                ], 400);
            }

            if (!empty($invoiceData['Prepayments'])) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Gagal Update: Invoice ini menggunakan Prepayment. Mohon edit manual di Xero.'
                ], 400);
            }

            // dd($payments);
            //save
            if (!empty($payments)) {
                foreach ($payments as $pay) {
                    $payId = $pay['PaymentID'];
                    // Backup data payment detail dari Xero ke DB Lokal
                    $this->backupPaymentData($payId);
                    // Simpan ID untuk direstore nanti
                    $paymentBackups[] = $payId;
                    // Hapus Payment di Xero agar Invoice bisa diedit
                    $this->voidPaymentInXero($payId);
                }

                // Refresh data invoice setelah payment dihapus (Status harusnya jadi AUTHORISED/DRAFT)
                $response = Http::withHeaders($this->getHeaders())->get($this->xeroBaseUrl . '/Invoices/' . $invoiceId);
                $invoiceData = $response->json()['Invoices'][0];
                $av_min = (int) $response->header('X-MinLimit-Remaining');
                $av_day = (int) $response->header('X-DayLimit-Remaining');
                $this->globalService->requestCalculationXero($av_min, $av_day);

                $currentLineItems = $invoiceData['LineItems'] ?? [];
            }

            // ------------------------------------------------------------------
            // LANGKAH 2: PROSES LOGIKA ITEM (Add/Edit)
            // ------------------------------------------------------------------
            $subtotal = $request->qty * $request->price;
            $discountRate = 0;
            if ($subtotal > 0 && $request->disc_amount > 0) {
                $discountRate = ($request->disc_amount / $subtotal) * 100;
            }

            // Susun Tracking
            $tracking = [];
            if ($request->filled('agent_id')) {
                $tracking[] = ['Name' => 'Agen', 'Option' => '', 'TrackingOptionID' => $request->agent_id];
            }
            if ($request->filled('divisi_id')) {
                $tracking[] = ['Name' => 'Divisi', 'Option' => '', 'TrackingOptionID' => $request->divisi_id];
            }

            if($request->filled('item_code')) {
                $response_for_code = Http::withHeaders($this->getHeaders())->get($this->xeroBaseUrl . '/Items/' .  $request->item_code);
                if (isset($response_for_code['Items'][0]["Code"])) {
                    $request->merge(['item_code' => $response_for_code['Items'][0]["Code"]]);
                    //$data_item_prod = $response_for_code['Items'][0]["Code"];
                } else {

                }
            }
            $response_for_code = Http::withHeaders($this->getHeaders())->get($this->xeroBaseUrl . '/Items/' .  $request->item_code);

            //dd($data_item_prod);
            //$request->item_code =  $data_item_prod;

            // Object Line Item Baru sebelum set item_code requred
            // $newLineItem = [
            //     'ItemCode'      => $request->item_code,
            //     'Description'   => $request->description,
            //     'Quantity'      => $request->qty,
            //     'UnitAmount'    => $request->price,
            //     'DiscountRate'  => round($discountRate, 4),
            //     'AccountCode'   => $request->account_code ?? '200',
            //     'TaxType'       => $request->tax_type ?? 'NONE',
            //     'Tracking'      => $tracking
            // ];

            $newLineItem = [
                'Description'   => $request->description, // Wajib jika tanpa item code
                'Quantity'      => $request->qty,
                'UnitAmount'    => $request->price,
                'DiscountRate'  => round($discountRate, 4),
                'AccountCode'   => $request->account_code ?? '200', // Default Sales jika null
                'TaxType'       => $request->tax_type ?? 'NONE',
                'Tracking'      => $tracking
            ];

            //new nullable
            if ($request->filled('item_code')) {
                $newLineItem['ItemCode'] = $request->item_code;
            }

            //dd($tracking);
            if ($request->filled('line_item_id')) {
                $newLineItem['LineItemID'] = $request->line_item_id;
            }

            // Merge dengan Item Lama
            $updatedLineItems = [];
            $found = false;

            foreach ($currentLineItems as $item) {
                if ($request->filled('line_item_id') && isset($item['LineItemID']) && $item['LineItemID'] == $request->line_item_id) {
                    $updatedLineItems[] = $newLineItem;
                    $found = true;
                } else {
                    // Copy item lama
                    // $updatedLineItems[] = [
                    //     'LineItemID' => $item['LineItemID'],
                    //     'Quantity'   => $item['Quantity'],
                    //     'UnitAmount' => $item['UnitAmount'],
                    //     'ItemCode'   => $item['ItemCode'] ?? null,
                    //     'Description'=> $item['Description'] ?? null,
                    //     'AccountCode'=> $item['AccountCode'] ?? null,
                    //     'TaxType'    => $item['TaxType'] ?? null,
                    //     'DiscountRate'=> $item['DiscountRate'] ?? 0,
                    //     'Tracking'   => $item['Tracking'] ?? []
                    // ];

                    $tempItem = [
                        'LineItemID' => $item['LineItemID'],
                        'Quantity'   => $item['Quantity'],
                        'UnitAmount' => $item['UnitAmount'],
                        'Description'=> $item['Description'] ?? null,
                        'AccountCode'=> $item['AccountCode'] ?? null,
                        'TaxType'    => $item['TaxType'] ?? null,
                        'DiscountRate'=> $item['DiscountRate'] ?? 0,
                        'Tracking'   => $item['Tracking'] ?? []
                    ];

                    if (isset($item['ItemCode']) && !empty($item['ItemCode'])) {
                        $tempItem['ItemCode'] = $item['ItemCode'];
                    }

                    $updatedLineItems[] = $tempItem;
                }
            }

            if (!$found && empty($request->line_item_id)) {
                $updatedLineItems[] = $newLineItem;
            }

            // ------------------------------------------------------------------
            // LANGKAH 3: UPDATE INVOICE KE XERO
            // ------------------------------------------------------------------
            $payload = [
                'InvoiceID' => $invoiceId,
                'LineItems' => $updatedLineItems
            ];

            $updateResponse = Http::withHeaders($this->getHeaders())
                ->post($this->xeroBaseUrl . '/Invoices/' . $invoiceId, $payload);

            //
            $av2_min = (int) $updateResponse->header('X-MinLimit-Remaining');
            $av2_day = (int) $updateResponse->header('X-DayLimit-Remaining');
            $this->globalService->requestCalculationXero($av2_min, $av2_day);

            if ($updateResponse->failed()) {
                 Log::info("insert or update invoices "." status ".$updateResponse->status());
                // Jika gagal, kembalikan payment (opsional, tapi disarankan)
                // $this->restorePayments($invoiceId, $paymentBackups);
                return response()->json(['status' => 'error', 'message' => 'Xero Update Failed: ' . $updateResponse->body()], 400);
            }

            $updatedInvoice = $updateResponse->json()['Invoices'][0];

            // ------------------------------------------------------------------
            // LANGKAH 4: RESTORE PAYMENT (Bayar Ulang)
            // ------------------------------------------------------------------
            if (!empty($paymentBackups)) {
                foreach ($paymentBackups as $oldPayId) {
                    $this->restorePayment($invoiceId, $oldPayId);
                }
                PaymentParams::where('invoice_id', $invoiceId)->delete();
            }

            //update
            $local_total_payment = $this->globalService->getTotalLocalPaymentByuuidInvoice($invoiceId);
            $total_xero = $updatedInvoice['Total'];//['AmountPaid'];
            $hasil_selisih = $this->globalService->hitungSelisih($total_xero, $local_total_payment, 2); //bcsub($total_xero, $local_total_payment, 2);
           // dd($updatedInvoice["Contact"]);
            $this->globalService->SavedInvoiceValue($invoiceId,
                    $updatedInvoice["InvoiceNumber"],
                    isset($updatedInvoice["Contact"]) && $updatedInvoice["Contact"]["Name"] ? $updatedInvoice["Contact"]["Name"] : 'null-name',
                    //$updatedInvoice["Contact"]["FirstName"],
                    $total_xero,
                    $local_total_payment,
                    $hasil_selisih
            );
            //update
            $view_req =  $this->globalService->getDataAvailabeRequestXero();
            return response()->json([
                'status' => 'success',
                'message' => 'Invoice updated successfully',
                'data' => $updatedInvoice,
                'request_min_tersisa_hari' => $view_req->available_request_day,
                'request_min_tersisa_menit'  => $view_req->available_request_min,
            ]);

        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // HELPER FUNCTIONS (PRIVATE)
    // =========================================================================

    /**
     * Ambil detail payment dari Xero dan simpan ke DB Lokal
     */
    private function backupPaymentData($paymentId)
    {
        $response = Http::withHeaders($this->getHeaders())->get($this->xeroBaseUrl . "/Payments/$paymentId");

        //BELUM SELESAI
        if ($response->failed()) {//payment kemungkinan sudah di hapus
            $cek_payment_sebelumnya =  PaymentParams::query('payments_id',$paymentId)->first();
            $all_payemnt =  PaymentParams::query('invoice_id',$cek_payment_sebelumnya->invoice_id)->get();
            dd(count($all_payemnt));
            //1. AMBIL PaymentsHistoryFix BY $cek_payment_sebelumnya->invoice_id
            //2. Create pAYMENT dari data di atas
            //3. hapus PaymentParams -> jika ada
            //payment sudah di hapus
            //cari di backup local
            throw new \Exception("Gagal Backup Payment: " . $response->body() ." id payments $paymentId");
        }

        $data = $response->json();
        if (empty($data['Payments'])) return;

        $payment = $data['Payments'][0];
        if ($payment['Status'] == 'DELETED') return; // Jangan backup yang sudah dihapus

        // Format Tanggal Xero (/Date(123123)/) ke Y-m-d
        $date = $this->parseXeroDate($payment["Date"]);
        Log::info("insert or update payemt_params per item ".$payment["Invoice"]["InvoiceID"]);
      //  dd($payment["Account"]["AccountID"]);
        //dd($payment);//f31fd27e-570e-4898-9697-835258ebdfb1
        PaymentParams::updateOrCreate(
            ['payments_id' => $paymentId],
            [
                'invoice_id' => $payment["Invoice"]["InvoiceID"],
                'account_code' => $payment["Account"]["AccountID"],//$payment['Account']['Code'] ?? '200', // Bisa Code atau AccountID
                'account_id' => $payment['Account']['AccountID'] ?? null,
                'date' => $date,
                'amount' => $payment["Amount"],
                'reference' => "Re-payment API ".$payment["Reference"],
                'bank_account_id'=>$this->getBankAccountFromPayment($paymentId)
            ]
        );
    }

    /**
     * Hapus (Void) Payment di Xero
     */
    private function voidPaymentInXero($paymentId)
    {
        $response = Http::withHeaders($this->getHeaders())
            ->post($this->xeroBaseUrl . "/Payments/$paymentId", ["Status" => "DELETED"]);

        if ($response->failed() && $response->status() != 404) {
             throw new \Exception("Gagal Void Payment: " . $response->body());
        }
    }


    public function getBankAccountFromPayment($paymentId)
    {
        $response = Http::withHeaders($this->getHeaders())
            ->get($this->xeroBaseUrl . '/Payments/' . $paymentId);
        if ($response->failed()) {
            return null; // Handle error
        }
        $paymentData = $response->json()['Payments'][0];
        $bankAccountId = $paymentData['Account']['AccountID'];

        $bankCode = $paymentData['Account']['Code'] ?? '-';
        return $bankAccountId;
    }
    /**
     * Buat Ulang (Restore) Payment di Xero setelah Invoice diedit
     */
   private function restorePayment($invoiceId, $oldPaymentId)
    {
        // Ambil data backup dari DB Lokal
        // Kita gunakan first() karena PaymentID Xero itu unik
        $backup = PaymentParams::where('payments_id', $oldPaymentId)->first();

        if (!$backup) {
            Log::warning("Backup data not found for payment: $oldPaymentId");
            return;
        }

        // 1. Ambil Data Invoice Terbaru dari Xero untuk Cek Sisa Tagihan (AmountDue) saat ini
        $resInv = Http::withHeaders($this->getHeaders())->get($this->xeroBaseUrl . "/Invoices/$invoiceId");

        if ($resInv->failed()) {
            Log::error("Gagal Cek Tagihan Baru saat Restore Payment: " . $resInv->body());
            return;
        }

        $av2_min = (int) $resInv->header('X-MinLimit-Remaining');
        $av2_day = (int) $resInv->header('X-DayLimit-Remaining');
        $this->globalService->requestCalculationXero($av2_min, $av2_day);

        $invoiceXero = $resInv->json()['Invoices'][0];

        // AmountDue adalah sisa tagihan yang harus dibayar saat ini
        $amountDue = (float)$invoiceXero['AmountDue'];
        $contactID = $invoiceXero['Contact']['ContactID'];

        // Nominal yang dulu pernah dibayarkan user
        $originalPaymentAmount = (float)$backup->amount;

        // Variabel penampung alokasi dana
        $payToInvoice = 0;
        $payToOverpayment = 0;

        // ------------------------------------------------------------------
        // LOGIKA PENENTUAN ALOKASI DANA
        // ------------------------------------------------------------------

        if ($amountDue > 0) {
            if ($originalPaymentAmount <= $amountDue) {
                // KASUS 1: Tagihan Invoice LEBIH BESAR atau SAMA DENGAN pembayaran lama.
                // Contoh: Tagihan baru 1.5jt, Pembayaran lama 1jt.
                // Action: Masukkan semua 1jt ke Invoice.
                // Result: Status Invoice jadi AWAITING PAYMENT (Sisa 500rb).

                $payToInvoice = $originalPaymentAmount;
                $payToOverpayment = 0;
            } else {
                // KASUS 2: Tagihan Invoice LEBIH KECIL dari pembayaran lama.
                // Contoh: Tagihan baru 800rb, Pembayaran lama 1jt.
                // Action: Bayar 800rb ke Invoice, 200rb ke Overpayment.
                // Result: Status Invoice PAID. User punya saldo 200rb.

                $payToInvoice = $amountDue;
                $payToOverpayment = $originalPaymentAmount - $amountDue;
            }
        } else {
            // KASUS 3: Invoice sudah lunas (0), tapi masih ada payment yang harus direstore.
            // Action: Semuanya masuk ke Overpayment.

            $payToInvoice = 0;
            $payToOverpayment = $originalPaymentAmount;
        }

        // Siapkan Data Umum
        $payDate = $backup->date;
        $accId   = $backup->account_id ?? $backup->account_code; // Utamakan ID,
        $ref     = $backup->reference;
        $bank_id = $backup->bank_account_id;

        // ------------------------------------------------------------------
        // EKSEKUSI A: POST PAYMENT (Bayar ke Invoice)
        // ------------------------------------------------------------------
        if ($payToInvoice > 0) {
            $payloadInvoicePay = [
                "Payments" => [[
                    "Invoice"   => ["InvoiceID" => $invoiceId],
                    "Account"   => ["AccountID" => $accId], // Pastikan AccountID Bank valid
                    "Date"      => $payDate,
                    "Amount"    => $payToInvoice,
                    "Reference" => "re payment by system item detail ".$ref
                ]]
            ];

            $resPay = Http::withHeaders($this->getHeaders())
                ->post($this->xeroBaseUrl . '/Payments', $payloadInvoicePay);

            $avP_min = (int) $resPay->header('X-MinLimit-Remaining');
            $avP_day = (int) $resPay->header('X-DayLimit-Remaining');
            $this->globalService->requestCalculationXero($avP_min, $avP_day);

            if ($resPay->failed()) {
                Log::error("Gagal Restore Payment ke Invoice: " . $resPay->body());
                // Jangan throw exception di sini agar logic Overpayment tetap jalan jika perlu
            } else {
                Log::info("Sukses Restore Payment Invoice: $payToInvoice");
            }
        }

        // ------------------------------------------------------------------
        // EKSEKUSI B: PUT BANK TRANSACTION (Overpayment / Saldo Mengendap)
        // ------------------------------------------------------------------
         Log::info("total overpayment $payToOverpayment");
        if ($payToOverpayment > 0) {
            Log::info("Membuat Overpayment (Saldo) sebesar: $payToOverpayment BankAccount $accId | ");
            $payloadOverpayment = [
                "BankTransactions" => [[
                    "Type"        => "RECEIVE-OVERPAYMENT",
                    "Contact"     => ["ContactID" => $contactID],
                    "BankAccount" => ["AccountID" => $accId],//Harus account dengan type bank yang bisa
                    "Date"        => $payDate,
                    "Reference"   => $ref . "overpayment (Ref: $invoiceXero[InvoiceNumber])",
                    "LineItems"   => [[
                        "Description" => "Overpayment pada Invoice " . $invoiceXero['InvoiceNumber'],
                        "UnitAmount"  => $payToOverpayment,
                        // AccountCode kosongkan agar Xero otomatis pakai Accounts Receivable / AP
                        // Atau isi dengan kode akun Liability (Hutang ke Customer) jika ada
                    ]]
                ]]
            ];

            $resOver = Http::withHeaders($this->getHeaders())
                ->put($this->xeroBaseUrl . '/BankTransactions', $payloadOverpayment);

            $rmin = (int) $resOver->header('X-MinLimit-Remaining');
            $rday = (int) $resOver->header('X-DayLimit-Remaining');
            $this->globalService->requestCalculationXero($rmin, $rday);

            if ($resOver->failed()) {
                Log::error("Gagal Restore Overpayment: " . $resOver->body());
                Log::error("error", $payloadOverpayment);
            } else {
                Log::info("Sukses Restore Overpayment: $payToOverpayment");
            }
        }
    }

    /**
     * Helper Format Date Xero (/Date(151515)/) -> Y-m-d
     */
    private function parseXeroDate($xeroDate) {
        if (preg_match('/\/Date\((\d+)([+-]\d+)?\)\//', $xeroDate, $matches)) {
            $timestamp = $matches[1] / 1000;
            return date('Y-m-d', $timestamp);
        }
        return date('Y-m-d'); // Default today
    }


    public function deleteItem(Request $request, $lineId)
    {
        $invoiceId = $request->invoice_id;

        // 1. Ambil Data Awal
        $response = Http::withHeaders($this->getHeaders())->get($this->xeroBaseUrl . '/Invoices/' . $invoiceId);
        if ($response->failed()) return response()->json(['status' => 'error'], 500);

        $rmin = (int) $response->header('X-MinLimit-Remaining');
        $rday = (int) $response->header('X-DayLimit-Remaining');
        $this->globalService->requestCalculationXero($rmin, $rday);


        $invoiceData = $response->json()['Invoices'][0];
        $payments = $invoiceData['Payments'] ?? [];
        $paymentBackups = [];
        //dd($payments);

        if (!empty($invoiceData['CreditNotes'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal Update: Invoice ini sudah dipotong Credit Note. Mohon lakukan "Remove Allocation" manual di Xero terlebih dahulu sebelum edit via sistem.'
            ], 400);
        }

        if (!empty($invoiceData['Prepayments'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal Update: Invoice ini menggunakan Prepayment. Mohon edit manual di Xero.'
            ], 400);
        }
        // 2. Backup & Void Payment (Jika Status PAID/Partial)
        //dd($payments);
        if (!empty($payments)) {
            Log::info("muali Menghapus item dari Paid Invoice: $invoiceId");

            foreach ($payments as $pay) {
                $payId = $pay['PaymentID'];
                $this->backupPaymentData($payId); // Backup ke DB
                $paymentBackups[] = $payId;       // Simpan ID untuk restore
                $this->voidPaymentInXero($payId); // Hapus di Xero
            }
            $response = Http::withHeaders($this->getHeaders())->get($this->xeroBaseUrl . '/Invoices/' . $invoiceId);
            $invoiceData = $response->json()['Invoices'][0];

            $a_min = (int) $response->header('X-MinLimit-Remaining');
            $a_day = (int) $response->header('X-DayLimit-Remaining');
            $this->globalService->requestCalculationXero($a_min, $a_day);
        }

        // 3. Filter Item (Hapus Item dari Array)
        $currentLineItems = $invoiceData['LineItems'] ?? [];
        $newLineItems = [];

        foreach ($currentLineItems as $item) {
            // Masukkan item ke array baru HANYA JIKA ID-nya TIDAK SAMA dengan yang mau dihapus
            if ($item['LineItemID'] != $lineId) {
                $newLineItems[] = $item;
            }
        }

        // 4. Kirim Update ke Xero (LineItems Baru)
        $payload = [
            'InvoiceID' => $invoiceId,
            'LineItems' => $newLineItems
        ];

        $tokenData = $this->getValidToken();
        if (!$tokenData) {
            return response()->json(['message' => 'Token kosong/invalid. Silakan akses /xero/connect dulu.'], 401);
        }

        $updateResponse = Http::withHeaders([
            'Authorization' => 'Bearer ' .$tokenData["access_token"],
            'Xero-Tenant-Id' => env('XERO_TENANT_ID'),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->post($this->xeroBaseUrl . '/Invoices/' . $invoiceId, $payload);

        if ($updateResponse->failed()) {
             // Opsional: Restore payment jika update gagal agar data tidak rusak
             // $this->restoreBatchPayments($invoiceId, $paymentBackups);
             return response()->json(['status' => 'error', 'message' => 'Gagal update Xero: ' . $updateResponse->body()], 400);
        }

        $Req_min = (int) $updateResponse->header('X-MinLimit-Remaining');
        $Req_day = (int) $updateResponse->header('X-DayLimit-Remaining');
        $this->globalService->requestCalculationXero($Req_min, $Req_day);

        // 5. Restore Payment (Jika tadi ada payment)
        if (!empty($paymentBackups)) {
            Log::info("Mengembalikan Payment untuk Invoice: $invoiceId");
            foreach ($paymentBackups as $oldPayId) {
                $this->restorePayment($invoiceId, $oldPayId);
                // Hapus data backup dari DB agar tidak menumpuk
                //PaymentParams::where('payments_id', $oldPayId)->delete();
            }
            PaymentParams::where('invoice_id', $invoiceId)->delete();
        }
        //update
        $local_total_payment = $this->globalService->getTotalLocalPaymentByuuidInvoice($invoiceId);
        $total_xero = $updateResponse['Invoices'][0]['Total'];//['AmountPaid'];
        $hasil_selisih = $this->globalService->hitungSelisih($total_xero, $local_total_payment, 2); //bcsub($total_xero, $local_total_payment, 2);
        $this->globalService->SavedInvoiceValue($invoiceId,
                $updateResponse['Invoices'][0]['InvoiceNumber'],
                isset($updateResponse['Invoices'][0]["Contact"]["FirstName"]) ? $updateResponse['Invoices'][0]["Contact"]["FirstName"] : 'null-name',
                // $updateResponse['Invoices'][0]["Contact"]["FirstName"],
                $total_xero,
                $local_total_payment,
                $hasil_selisih
        );
        //update

        return response()->json(['status' => 'success', 'message' => 'Item deleted from Xero']);
    }
}
