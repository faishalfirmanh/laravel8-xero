<?php

namespace App\Http\Controllers\Xero;
use App\ConfigRefreshXero;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\PaymentParams;
use App\Models\InvoicePriceGap;
use Illuminate\Support\Facades\Log;
class InvoicesController extends Controller
{

    use ConfigRefreshXero;
    public function viewProduct()
    {
        return view('product');
    }

    public function getAllInvoicesOri(Request $request)
    {
        $i = 0;
        try {
            // Panggilan dilakukan dari SISI SERVER, BUKAN BROWSER
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . env('BARER_TOKEN'),
                'Xero-Tenant-Id' => '90a3a97b-3d70-41d3-aa77-586bb1524beb',
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->get('https://api.xero.com/api.xro/2.0/Invoices');

            if ($response->status() == 200) {
                $list_invoices_id = [];
                foreach ($response->json()['Invoices'] as $key => $value) {
                    $list_invoices_id[] = $value['InvoiceID'];

                }
                //1. looping id invoices
                foreach ($list_invoices_id as $key2 => $value2) {
                    //2. cek detail invoices
                    $cleanId = trim($value2, '"');
                    $resp2 = Http::withHeaders([
                        'Authorization' => 'Bearer ' . env('BARER_TOKEN'),
                        'Xero-Tenant-Id' => '90a3a97b-3d70-41d3-aa77-586bb1524beb',
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ])->get("https://api.xero.com/api.xro/2.0/Invoices/$cleanId");//$cleanId
                    //5a3e4380-5bec-4cba-bebe-19a796475ca0

                    $tiap_item = $resp2->json()['Invoices'][0]['LineItems'];
                    foreach ($tiap_item as $key3 => $value3) {
                        $qty_detail_item = $value3['Quantity'];
                        $getProduct = Http::withHeaders([
                            'Authorization' => 'Bearer ' . env('BARER_TOKEN'),
                            'Xero-Tenant-Id' => '90a3a97b-3d70-41d3-aa77-586bb1524beb',
                            'Content-Type' => 'application/json',
                            'Accept' => 'application/json',
                        ])->get("https://api.xero.com/api.xro/2.0/Items/" . $value3['ItemCode']);

                        //dd($getProduct->json()['Items']);
                        $price_origin_product = $getProduct->json()['Items'][0]['SalesDetails']['UnitPrice'];
                        //dd($getProduct->json()['Items'][0]['SalesDetails']['UnitPrice']);
                        $payload_2 = [
                            "InvoiceID" => $value2,
                            "LineItems" => [
                                [
                                    "LineItemID" => $value3['LineItemID'],
                                    "UnitAmount" => $price_origin_product,
                                    "Quantity" => $qty_detail_item
                                ]
                            ]
                        ];
                        $update_tiap_row_invoices = Http::withHeaders([
                            'Authorization' => 'Bearer ' . env('BARER_TOKEN'),
                            'Xero-Tenant-Id' => '90a3a97b-3d70-41d3-aa77-586bb1524beb',
                            'Content-Type' => 'application/json',
                            'Accept' => 'application/json',
                        ])->post('https://api.xero.com/api.xro/2.0/Invoices', $payload_2);
                        $i++;
                    }
                }
            }

            return response()->json(['message' => 'success', 'data' => $i]);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Proxy Error: ' . $e->getMessage()], 500);
        }
    }


    function xeroDateToPhp($xeroDate, $format = 'Y-m-d') {
        if (empty($xeroDate)) return null;
        preg_match('/\/Date\((-?\d+)/', $xeroDate, $matches);

        if (!isset($matches[1])) return null;
        return date($format, $matches[1] / 1000);
    }

    private function voidPaymentInXero($paymentId)
    {
        $response = Http::withHeaders($this->getHeaders())
            ->post($this->xeroBaseUrl . "/Payments/$paymentId", ["Status" => "DELETED"]);
        if ($response->failed() && $response->status() != 404) {
             throw new \Exception("Gagal Void Payment: " . $response->body());
        }
    }


    public function forceDeleteInvoice(string $uuid_inv)
    {
        try {
            $tokenData = $this->getValidToken();
            if (!$tokenData) {
                return response()->json([
                    'message' => 'Token kosong/invalid. Silakan akses /xero/connect dulu.'
                ], 401);
            }

            $headers = [
                'Authorization' => 'Bearer ' . $tokenData["access_token"],
                'Xero-Tenant-Id' => env('XERO_TENANT_ID'),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ];

             $invoiceResp = Http::withHeaders($headers)
            ->get("https://api.xero.com/api.xro/2.0/Invoices/{$uuid_inv}");

        if (!$invoiceResp->successful()) {
            return response()->json([
                'message' => 'Invoice tidak ditemukan',
                'error' => $invoiceResp->json()
            ], $invoiceResp->status());
        }


        $invoice = $invoiceResp->json('Invoices.0');
        $currentStatus = $invoice['Status'];
        // 2️⃣ Hapus semua payment (reconciled atau tidak)
       if (!empty($invoice['Payments'])) {
            foreach ($invoice['Payments'] as $payment) {
                $paymentId = $payment['PaymentID'] ?? null;

                if (!$paymentId) continue;

                // FIX: Xero Payment dihapus dengan POST update Status ke 'DELETED'
                // Bukan menggunakan HTTP DELETE
                $deletePayment = Http::withHeaders($headers)
                    ->post("https://api.xero.com/api.xro/2.0/Payments/{$paymentId}", [
                        'Payments' => [
                            [
                                'PaymentID' => $paymentId,
                                'Status'    => 'DELETED'
                            ]
                        ]
                    ]);

                if (!$deletePayment->successful()) {
                    Log::error('Gagal menghapus payment Xero', [
                        'payment_id' => $paymentId,
                        'response' => $deletePayment->json()
                    ]);

                    // Opsional: Return error atau continue (tergantung kebijakan bisnis)
                    // Di sini kita return error agar proses berhenti jika payment gagal dihapus
                    return response()->json([
                        'message' => 'Gagal menghapus payment. Kemungkinan payment sudah di-reconcile bank.',
                        'payment_id' => $paymentId,
                        'error' => $deletePayment->json()
                    ], 400);
                }
            }
        }

        $targetStatus = in_array($currentStatus, ['DRAFT', 'SUBMITTED']) ? 'DELETED' : 'VOIDED';

        $deleteInvoice = Http::withHeaders($headers)
            ->post("https://api.xero.com/api.xro/2.0/Invoices/{$uuid_inv}", [
                'Invoices' => [
                    [
                        'InvoiceID' => $uuid_inv,
                        'Status'    => $targetStatus
                    ]
                ]
            ]);


        if (!$deleteInvoice->successful()) {
            Log::error("gagal hapus invoice ",$deleteInvoice->json());
            return response()->json([
                'message' => 'Gagal mengubah status invoice',
                'target_status' => $targetStatus,
                'error' => $deleteInvoice->json()
            ], $deleteInvoice->status());
        }

        return response()->json([
            'message' => 'Invoice dan semua payment berhasil dihapus',
            'invoice_id' => $uuid_inv
        ], 200);


        } catch (\Throwable $e) {

            Log::error('Force delete invoice error', [
                'invoice_id' => $uuid_inv,
                'exception' => $e
            ]);

            return response()->json([
                'message' => 'Internal Server Error'
            ], 500);
        }
    }

   public function forceVoidOverpaymentPayment(string $paymentId)
    {
        $tokenData = $this->getValidToken();

        $headers = [
            'Authorization'  => 'Bearer ' . $tokenData['access_token'],
            'Xero-Tenant-Id' => env('XERO_TENANT_ID'),
            'Content-Type'   => 'application/json',
            'Accept'         => 'application/json',
        ];

        // 1️⃣ Ambil Payment
        $paymentResp = Http::withHeaders($headers)
            ->get("https://api.xero.com/api.xro/2.0/Payments/{$paymentId}");

        if (!$paymentResp->successful()) {
            return response()->json([
                'message' => 'Payment / Overpayment tidak ditemukan',
                'error' => $paymentResp->json()
            ], 404);
        }

        $payment = $paymentResp->json('Payments.0');

        // 2️⃣ VOID / DELETE Payment
        $delete = Http::withHeaders($headers)
            ->post("https://api.xero.com/api.xro/2.0/Payments/{$paymentId}", [
                'Payments' => [
                    [
                        'PaymentID' => $paymentId,
                        'Status'    => 'DELETED'
                    ]
                ]
            ]);

        if (!$delete->successful()) {
            return response()->json([
                'message' => 'Gagal menghapus overpayment payment',
                'error' => $delete->json()
            ], 400);
        }

        return response()->json([
            'message' => 'Overpayment (Payment) berhasil dihapus',
            'payment_id' => $paymentId
        ]);
    }



    public function forceDeleteCreditNote(string $creditNoteId)
    {
        try {
            $tokenData = $this->getValidToken();
            if (!$tokenData) {
                return response()->json([
                    'message' => 'Token kosong/invalid. Silakan akses /xero/connect dulu.'
                ], 401);
            }

            $headers = [
                'Authorization'  => 'Bearer ' . $tokenData['access_token'],
                'Xero-Tenant-Id' => env('XERO_TENANT_ID'),
                'Content-Type'   => 'application/json',
                'Accept'         => 'application/json',
            ];

            // 1️⃣ Ambil Credit Note
            $creditNoteResp = Http::withHeaders($headers)
                ->get("https://api.xero.com/api.xro/2.0/CreditNotes/{$creditNoteId}");

            //dd($creditNoteResp);
            if (!$creditNoteResp->successful()) {
                return response()->json([
                    'message' => 'Credit Note tidak ditemukan',
                    'error'   => $creditNoteResp->json()
                ], $creditNoteResp->status());
            }

            $creditNote = $creditNoteResp->json('CreditNotes.0');
            $currentStatus = $creditNote['Status'];

            // 2️⃣ Hapus semua Allocation (Credit Note → Invoice)
            if (!empty($creditNote['Allocations'])) {
                foreach ($creditNote['Allocations'] as $allocation) {
                    $allocationId = $allocation['AllocationID'] ?? null;

                    if (!$allocationId) continue;

                    $deleteAllocation = Http::withHeaders($headers)
                        ->delete(
                            "https://api.xero.com/api.xro/2.0/CreditNotes/{$creditNoteId}/Allocations/{$allocationId}"
                        );

                    if (!$deleteAllocation->successful()) {
                        Log::error('Gagal menghapus allocation credit note', [
                            'credit_note_id' => $creditNoteId,
                            'allocation_id'  => $allocationId,
                            'response'       => $deleteAllocation->json()
                        ]);

                        return response()->json([
                            'message'        => 'Gagal menghapus allocation credit note',
                            'allocation_id' => $allocationId,
                            'error'         => $deleteAllocation->json()
                        ], 400);
                    }
                }
            }

            // 3️⃣ Hapus semua Refund (jika ada)
            if (!empty($creditNote['Refunds'])) {
                foreach ($creditNote['Refunds'] as $refund) {
                    $refundId = $refund['RefundID'] ?? null;

                    if (!$refundId) continue;

                    // Refund dihapus dengan update Status = DELETED
                    $deleteRefund = Http::withHeaders($headers)
                        ->post("https://api.xero.com/api.xro/2.0/Refunds/{$refundId}", [
                            'Refunds' => [
                                [
                                    'RefundID' => $refundId,
                                    'Status'   => 'DELETED'
                                ]
                            ]
                        ]);

                    if (!$deleteRefund->successful()) {
                        Log::error('Gagal menghapus refund credit note', [
                            'refund_id' => $refundId,
                            'response'  => $deleteRefund->json()
                        ]);

                        return response()->json([
                            'message'   => 'Gagal menghapus refund credit note',
                            'refund_id'=> $refundId,
                            'error'    => $deleteRefund->json()
                        ], 400);
                    }
                }
            }

            // 4️⃣ Tentukan target status
            $targetStatus = in_array($currentStatus, ['DRAFT', 'SUBMITTED'])
                ? 'DELETED'
                : 'VOIDED';

            // 5️⃣ Update status Credit Note
           $voidCreditNote = Http::withHeaders($headers)
            ->post("https://api.xero.com/api.xro/2.0/CreditNotes/{$creditNoteId}", [
                'CreditNotes' => [
                    [
                        'CreditNoteID' => $creditNoteId,
                        'Status'       => 'VOIDED'
                    ]
                ]
            ]);

            if (!$voidCreditNote->successful()) {
                Log::error('Gagal mengubah status credit note', $voidCreditNote->json());

                return response()->json([
                    'message'       => 'Gagal mengubah status credit note',
                    'target_status' => $targetStatus,
                    'error'         => $voidCreditNote->json()
                ], $voidCreditNote->status());
            }

            return response()->json([
                'message'        => 'Credit Note berhasil dihapus (force delete)',
                'credit_note_id'=> $creditNoteId
            ], 200);

        } catch (\Throwable $e) {
            Log::error('Force delete credit note error', [
                'credit_note_id' => $creditNoteId,
                'exception'      => $e
            ]);

            return response()->json([
                'message' => 'Internal Server Error'
            ], 500);
        }
    }


    public function getDetailInvoice($idInvoice)
    {
        try {

            $tokenData = $this->getValidToken();
            if (!$tokenData) {
                return response()->json(['message' => 'Token kosong/invalid. Silakan akses /xero/connect dulu.'], 401);
            }

            $response_detail = Http::withHeaders([
                'Authorization' => 'Bearer ' . $tokenData["access_token"],
                'Xero-Tenant-Id' => env('XERO_TENANT_ID'),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->get("https://api.xero.com/api.xro/2.0/Invoices/$idInvoice");

            $data_response = $response_detail->json();
            $inv = InvoicePriceGap::where('invoice_uuid',$idInvoice)->first();
            //dd($inv);
            $data_response["custom"] = [
                'id_invoice'=>$inv ? $inv->invoice_number : 0,
                'contact_name'=>$inv ? $inv->contact_name : 0,
                'total_xero' =>$inv ? $inv->total_nominal_payment_xero : 0,
                'total_local' =>$inv ? $inv->total_nominal_payment_local : 0,
                'total_price_return'=>$inv ? $inv->total_price_return : 0
            ];
            return response()->json($data_response ?: ['message' => 'Xero API Error'], $response_detail->status());
        } catch (\Exception $e) {
            return response()->json(['message' => 'Proxy Error: ' . $e->getMessage()], $e->getCode());
        }

    }

    public function viewDetailInvoice($idInvoice)
    {
        return view('detail_invoices2');
    }

    public function updateInvoiceSelected(Request $request)
    {
        $rawContent = $request->getContent();
        $data = json_decode($rawContent, true);
        $tot = 0;
        $array = [];
        foreach ($data['items'] as $key => $value) {
            //dd($value);
               if($value['no_payment'] != "kosong"){
                //dd($value['no_payment']);
                self::getDetailPayment($value['no_payment']);
                self::updateInvoicePaidPerRows($value['no_payment']);
               }
                self::updateInvoicePerRows($value['parentId'], $data['price_update'], $value['lineItemId'],$value['status']);
                if($value['no_payment'] != "kosong"){
                    self::createPayments($value['parentId']);
                }
                $tot++;
                $array[] = $value['no_invoice'];

        }
        return response()->json($array, 200);
    }

    public function getDetailPayment($idPayment)
    {
         try {
            $tokenData = $this->getValidToken();
            if (!$tokenData) {
                return response()->json(['message' => 'Token kosong/invalid. Silakan akses /xero/connect dulu.'], 401);
            }
            $response_detail = Http::withHeaders([
                'Authorization' => 'Bearer ' . $tokenData["access_token"],
                'Xero-Tenant-Id' => env("XERO_TENANT_ID"),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->get("https://api.xero.com/api.xro/2.0/Payments/$idPayment");

          // return response()->json($response_detail->json() ?: ['message' => 'Xero API Error'], $response_detail->status());
           // dd($response_detail['Payments'][0]["Amount"]);
            $amount = $response_detail['Payments'][0]["Amount"];
            $account_code = $response_detail['Payments'][0]["Account"]["AccountID"];
            $date = self::xeroDateToPhp($response_detail['Payments'][0]["Date"]) ;
            $invoice_id = $response_detail['Payments'][0]["Invoice"]["InvoiceID"];
           // dd($invoice_id);
            $reference_id ="$invoice_id update harga otomatis xero paid";
            self::insertToDb($amount,$account_code,$date, $invoice_id, $reference_id);
            //dd($invoice_id);
           // $account_code =
           //  self::insertToDb();
           // return response()->json($response_detail->json() ?: ['message' => 'Xero API Error'], $response_detail->status());
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error insert db get detail payment : ' . $e->getMessage()], $e->getCode());
        }
    }

    public function insertToDb($amount,$account_code,$date, $invoice_id, $reference_id)
    {
        PaymentParams::create([
            'invoice_id'=>$invoice_id,
            'account_code'=>$account_code,
            'date'=>$amount,
            'amount'=>$amount,
            'reference'=>$reference_id
        ]);
    }

    public function createPayments($invoice_id)
    {
        $invoice_table = PaymentParams::where('invoice_id',$invoice_id)->first();
        $form = [
            "Payments" => [
            [
                "Invoice" => [
                    "InvoiceID" => $invoice_id
                ],
                "Account" => [
                    "Code" => $invoice_table->account_code
                ],
                "Date" => $invoice_table->date,
                "Amount" => $invoice_table->amount,
                "Reference" => $invoice_table->reference ?? "Payment via API",
            ]
        ]
        ];
         $update_tiap_row_invoices = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('BARER_TOKEN'),
            'Xero-Tenant-Id' => '90a3a97b-3d70-41d3-aa77-586bb1524beb',
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->post('	https://api.xero.com/api.xro/2.0/Payments', $form);
    }


    public function updateInvoicePaidPerRows($payment_id)
    {
        $inv = [];
        $update_payment = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('BARER_TOKEN'), // Sebaiknya ganti ke config('xero.token') nanti
            'Xero-Tenant-Id' => '90a3a97b-3d70-41d3-aa77-586bb1524beb',
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->post("https://api.xero.com/api.xro/2.0/Payments/$payment_id",  [ "Status" => 'DELETED' ]);

        if ($update_payment->failed()) {
            return response()->json([
                'error' => true,
                'message' => 'Gagal update Payment',
                'details' => $update_payment->json()
            ], $update_payment->status());
        }
    }

    //untuk yang draft dan awaiting payment
    public function updateInvoicePerRows($parent_id, $amount_input, $line_item_id,$status_invoice)
    {
        // $parent_id = $request->parent_invoice;
        //input invoice_id, amount (harga paket setelah update), line_items

        $cleanId = str_replace('"', '', $parent_id);//trim($parent_id, '"');
        //dd($cleanId);
        $response_detail = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('BARER_TOKEN'),
            'Xero-Tenant-Id' => '90a3a97b-3d70-41d3-aa77-586bb1524beb',
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->get("https://api.xero.com/api.xro/2.0/Invoices/$cleanId");
        //  dd($response_detail);

        $all_itemm = [];

        foreach ($response_detail['Invoices'] as $key => $value) {//list per paket
            foreach ($value['LineItems'] as $key2 => $value2) {//item tiap paket
                // $cek_is_update_Qty = $value2['LineItemID'] == $request->line_item_id ? $request->qty_input : $value2['Quantity'];
                $cek_is_update_Amount = $value2['LineItemID'] == $line_item_id ? $amount_input : $value2['UnitAmount'];
             //   dd($value2)
                $all_itemm[] = [
                    'LineItemID' => $value2['LineItemID'],
                    'ItemCode' => $value2['ItemCode'],
                    'Description' => 'update harga paket ' . $value2['Item']['Name'],
                    'UnitAmount' => $cek_is_update_Amount,
                    'Quantity' => $value2['Quantity'],
                    'AccountID'=>$value2['AccountID'],
                    'TaxType'=>$value2['TaxType']
                ];
            }
        }
        $parent_wrap = ['InvoiceID' => $parent_id, 'LineItems' => $all_itemm];

        $update_tiap_row_invoices = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('BARER_TOKEN'),
            'Xero-Tenant-Id' => '90a3a97b-3d70-41d3-aa77-586bb1524beb',
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->post('https://api.xero.com/api.xro/2.0/Invoices', $parent_wrap);
        // dd($update_tiap_row_invoices->status());//200
        return response()->json($update_tiap_row_invoices->json() ?: ['message' => 'Xero API Error'], $update_tiap_row_invoices->status());
    }

    public function listAllInvoices()
    {

        $tokenData = $this->getValidToken();

        if (!$tokenData) {
            return response()->json(['message' => 'Token kosong/invalid.'], 401);
        }

        $invoicesResponse = Http::withHeaders([
            'Authorization' => 'Bearer ' . $tokenData['access_token'],
            'Xero-Tenant-Id' => env('XERO_TENANT_ID'),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->get('https://api.xero.com/api.xro/2.0/Invoices');

        if ($invoicesResponse->failed()) {
            return response()->json(['error' => 'Gagal ambil invoices'], 500);
        }

          return response()->json([
            'data'=>$invoicesResponse->json()['Invoices'],
            'total'=>count($invoicesResponse->json()['Invoices'])
          ]);

    }

    public function getInvoiceByIdPaketPaging(Request $request, $itemCode = 0)
    {
        try {
            // 1. Ambil Parameter Request
            $page = (int) $request->input('page', 1);
            $limit = (int) $request->input('limit', 10);
            $search = strtolower($request->input('search', '')); // Search Nama Jamaah

            // 2. Validasi Token
            $tokenData = $this->getValidToken();
            if (!$tokenData) {
                return response()->json(['message' => 'Token kosong/invalid.'], 401);
            }

            // 3. Ambil Semua Invoice dari Xero
            // CATATAN: Ini berat jika invoice ribuan. Idealnya pakai parameter filter Xero (?where=...)
            // tapi struktur Xero API Invoice tidak bisa filter nested ItemCode langsung.
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $tokenData['access_token'],
                'Xero-Tenant-Id' => env('XERO_TENANT_ID'),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->get('https://api.xero.com/api.xro/2.0/Invoices');

            if ($response->failed()) {
                return response()->json(['message' => 'Gagal ambil data Xero'], 500);
            }

            $allInvoices = $response['Invoices'];
            $filteredList = [];

            // 4. Looping & Filtering Data (Cari ItemCode & Search Nama)
            foreach ($allInvoices as $value) {
                $cleanId = trim($value['InvoiceID'], '"');

                // OPTIMASI: Cek dulu apakah nama jamaah cocok (jika ada search), sebelum detail request
                $namaJamaah = isset($value['Contact']['Name']) ? strtolower($value['Contact']['Name']) : '';

                if ($search !== '' && strpos($namaJamaah, $search) === false) {
                    continue; // Skip jika nama tidak cocok dengan search
                }

                // Request Detail Invoice
                $resp2 = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $tokenData['access_token'],
                    'Xero-Tenant-Id' => env('XERO_TENANT_ID'),
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])->get("https://api.xero.com/api.xro/2.0/Invoices/$cleanId");

                if(isset($resp2['Invoices'][0]['LineItems'])){
                    foreach ($resp2['Invoices'][0]['LineItems'] as $lineItem) {
                        if (isset($lineItem['ItemCode']) && $lineItem['ItemCode'] == $itemCode) {

                            $invDetail = $resp2['Invoices'][0];

                            $filteredList[] = [
                                'parent_invoice_id' => $cleanId,
                                'nama_jamaah' => $invDetail['Contact']['Name'],
                                'no_invoice' => $value['InvoiceNumber'],
                                'line_item_id' => $lineItem['LineItemID'],
                                'detail_amount_tiap_item' => $lineItem['LineAmount'],
                                'qty_tiap_item' => $lineItem['Quantity'],
                                'tanggal' => $invDetail['DateString'],
                                'tanggal_due_date' => $invDetail['DueDateString'] ?? null,
                                'paket_name' => $lineItem['Item']['Name'] ?? '',
                                'amount_paid' => $invDetail['AmountPaid'],
                                'total' => $invDetail['Total'],
                                'status' => $value['Status'],
                                'payment' => $value['Payments'] ?? []
                            ];
                        }
                    }
                }
            }

            // 5. Pagination Manual (Array Slice)
            $totalData = count($filteredList);
            $totalPages = ceil($totalData / $limit);
            $offset = ($page - 1) * $limit;

            $paginatedData = array_slice($filteredList, $offset, $limit);

            // 6. Return JSON dengan Meta Data Pagination
            return response()->json([
                'data' => $paginatedData,
                'meta' => [
                    'current_page' => $page,
                    'limit' => $limit,
                    'total_data' => $totalData,
                    'total_pages' => $totalPages
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function getInvoicesAll(Request $request)
    {
        // Default limit kita set 10
        $limit = 10;
        $clientPage = (int) $request->query('page', 1);

        try {
            $tokenData = $this->getValidToken();
            if (!$tokenData) {
                return response()->json(['message' => 'Token kosong/invalid.'], 401);
            }

            /**
             * LOGIKA LIMIT 10:
             * Karena Xero selalu kasih 100 data per page, kita hitung
             * xero_page mana yang harus dipanggil.
             * Contoh: Client minta page 1-10 -> Semua ada di Xero page 1.
             * Client minta page 11 -> Ambil dari Xero page 2.
             */
            $xeroPage = ceil(($clientPage * $limit) / 100);

            $tenantId = env('XERO_TENANT_ID');

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $tokenData['access_token'],
                'Xero-Tenant-Id' => $tenantId,
                'Accept' => 'application/json',
            ])->get('https://api.xero.com/api.xro/2.0/Invoices', [
                'page' => $xeroPage,
                'where' => 'Status!="DELETED" AND Status!="VOIDED"',
                'order' => 'Date DESC'
            ]);

            if ($response->failed()) {
                return response()->json(['status' => 'error', 'message' => 'Gagal ambil data'], $response->status());
            }

            $allInvoices = $response->json()['Invoices'] ?? [];

            /**
             * SLICE DATA:
             * Mengambil 10 data yang spesifik dari 100 data yang dikirim Xero
             */
            $offset = (($clientPage - 1) * $limit) % 100;
            $slicedInvoices = array_slice($allInvoices, $offset, $limit);

            $list_invoice = [];
            foreach ($slicedInvoices as $invoice) {
                $lineItems = [];
                if (isset($invoice['LineItems'])) {
                    foreach ($invoice['LineItems'] as $item) {
                        $lineItems[] = [
                            'line_item_id' => $item['LineItemID'] ?? null,
                            'item_code'    => $item['ItemCode'] ?? null,
                            'paket_name'   => $item['Item']['Name'] ?? $item['Description'] ?? '-',
                            'qty'          => $item['Quantity'] ?? 0,
                            'amount'       => $item['LineAmount'] ?? 0,
                        ];
                    }
                }

                $list_invoice[] = [
                    'parent_invoice_id' => $invoice['InvoiceID'],
                    'nama_jamaah'       => $invoice['Contact']['Name'] ?? '-',
                    'no_invoice'        => $invoice['InvoiceNumber'],
                    'tanggal'           => $invoice['DateString'] ?? '',
                    'amount_paid'       => $invoice['AmountPaid'] ?? 0,
                    'total'             => $invoice['Total'] ?? 0,
                    'status'            => $invoice['Status'],
                    'items'             => $lineItems,
                ];
            }

            // Cek apakah masih ada data setelah data terakhir yang kita ambil
            $hasMore = true;
            if (count($allInvoices) < 100 && ($offset + $limit) >= count($allInvoices)) {
                $hasMore = false;
            }

            return response()->json([
                'current_page' => $clientPage,
                'per_page'     => $limit,
                'data'         => $list_invoice,
                'has_more'     => $hasMore
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function getInvoiceByIdPaket($itemCode = 0)
    {
         $string_final_code =  str_replace(
                    ['-', '+'],
                    ['/', '-'],
                    $itemCode
                );
        set_time_limit(0); // Biarkan berjalan lama jika data banyak

        try {
            $tokenData = $this->getValidToken();
            if (!$tokenData) {
                Log::error('Xero API Error Page token gagal api list invoices');
                return response()->json(['message' => 'Token kosong/invalid. Silakan akses /xero/connect dulu.'], 401);
            }

            $tenantId = env('XERO_TENANT_ID');
            $list_invoice = [];
            $page = 1;
            $hasMoreData = true;

            // Loop untuk mengambil data per halaman (100 data per request)
            // Ini jauh lebih cepat daripada mengambil detail satu per satu
            do {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $tokenData['access_token'],
                    'Xero-Tenant-Id' => $tenantId,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])->get('https://api.xero.com/api.xro/2.0/Invoices', [
                    'page' => $page, // Parameter paging Xero
                    //'where' => 'Status!="DELETED" AND Status!="VOIDED"' // Filter opsional biar lebih ringan
                ]);

                // Cek Error Request
                if ($response->failed()) {
                    Log::error('Xero API Error Page ' . $page . ':', [
                        'url' => 'GET /Invoices',
                        'status' => $response->status(),
                        'response' => $response->json()
                    ]);
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Gagal ambil data invoices pada halaman ' . $page,
                        'xero_status_code' => $response->status(),
                        'xero_error_detail' => $response->json()
                    ], $response->status());
                }

                $invoices = $response->json()['Invoices'];

                // Jika halaman ini kosong, berarti data sudah habis
                if (empty($invoices)) {
                    $hasMoreData = false;
                    break;
                }

                // Loop data dari halaman ini (Data Memory Lokal, bukan API Call)
                foreach ($invoices as $invoice) {
                    // Pastikan LineItems ada
                    if (!isset($invoice['LineItems']) || empty($invoice['LineItems'])) {
                        continue;
                    }

                    foreach ($invoice['LineItems'] as $item) {
                        // Filter berdasarkan ItemCode
                        if (isset($item['ItemCode']) && $item['ItemCode'] == $string_final_code) {

                            // Struktur data SAMA PERSIS dengan kode lama Anda
                            $list_invoice[] = [
                                'parent_invoice_id' => $invoice['InvoiceID'],
                                'nama_jamaah' => $invoice['Contact']['Name'] ?? '-',
                                'no_invoice' => $invoice['InvoiceNumber'],
                                'line_item_id' => $item['LineItemID'],
                                'detail_amount_tiap_item' => $item['LineAmount'],
                                'qty_tiap_item' => $item['Quantity'],
                                'tanggal' => $invoice['DateString'] ?? '',
                                'tanggal_due_date' => $invoice['DueDateString'] ?? null,
                                // Handle jika Item object tidak terload sempurna (safety check)
                                'paket_name' => $item['Item']['Name'] ?? $item['Description'] ?? '-',
                                'amount_paid' => $invoice['AmountPaid'],
                                'total' => $invoice['Total'],
                                'status' => $invoice['Status'],
                                'payment' => $invoice['Payments'] ?? []
                            ];
                        }
                    }
                }

                // Jika jumlah data kurang dari 100, berarti ini halaman terakhir
                if (count($invoices) < 100) {
                    $hasMoreData = false;
                } else {
                    $page++; // Lanjut ke halaman berikutnya
                    sleep(1); // Jeda 1 detik agar aman dari Rate Limit Xero (60req/menit)
                }

            } while ($hasMoreData);

            // Return Response (Logic sama seperti sebelumnya)
            if (count($list_invoice) > 0) {
                return response()->json($list_invoice, 200);
            } else {
                return response()->json(['message' => 'Data tidak ditemukan atau Xero API Error'], 404);
            }

        } catch (\Exception $e) {
            return response()->json(['message' => 'Proxy Error: ' . $e->getMessage()], 500);
        }
    }

    public function getInvoiceByIdPaketOld($itemCode = 0)
    {
        //  dd($itemCode);
        set_time_limit(0);
        try {

            $tokenData = $this->getValidToken();
            if (!$tokenData) {
                return response()->json(['message' => 'Token kosong/invalid. Silakan akses /xero/connect dulu.'], 401);
            }

            //dd($tokenData);
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $tokenData['access_token'],
                'Xero-Tenant-Id' => env('XERO_TENANT_ID'),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->get('https://api.xero.com/api.xro/2.0/Invoices');

            if ($response->failed()) {
                $errorDetail = $response->json();
                $statusCode = $response->status();
                Log::error('Xero API Error get list invoices :', [
                    'url' => 'GET /api/getInvoiceByIdPaket/'.$itemCode,
                    'status' => $statusCode,
                    'response' => $errorDetail
                ]);
                return response()->json([
                    'status' => 'error',
                    'message' => 'Gagal ambil data items',
                    'xero_status_code' => $statusCode,
                    'xero_error_detail' => $errorDetail
                ], $statusCode);
            }

            //dd($response['Invoices']);
            $items_inv = 0;
            $list_invoice = [];
            foreach ($response['Invoices'] as $key => $value) {
                $cleanId = trim($value['InvoiceID'], '"');//invoiceId

                //detail Invoices
                $resp2 = Http::withHeaders([
                    'Authorization' => 'Bearer ' .$tokenData['access_token'],
                    'Xero-Tenant-Id' => env('XERO_TENANT_ID'),
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])->get("https://api.xero.com/api.xro/2.0/Invoices/$cleanId");

                 if ($resp2->failed()) {
                    $errorDetail_2 = $resp2->json();
                    $statusCode_2 = $resp2->status();
                    Log::error('Xero API Error get details:', [
                        'url' => 'GET /Items : https://api.xero.com/api.xro/2.0/Invoices/'.$cleanId,
                        'status' => $statusCode_2,
                        'response' => $errorDetail_2
                    ]);
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Gagal ambil detail invoices',
                        'xero_status_code' => $statusCode_2,
                        'xero_error_detail' => $errorDetail_2
                    ], $statusCode_2);
                }

                foreach ($resp2['Invoices'] as $key2 => $value2) {//items

                    foreach ($value2['LineItems'] as $key3 => $value3) {//list
                        // dd($value2);
                        //$cek_detail_row = isset($list_invoice[$cleanId]) && $list_invoice[$cleanId]['line_item_id'] === $value3['LineItemID'];

                        if (isset($value3['ItemCode']) && $value3['ItemCode'] == $itemCode) {
                            //  dd($value3);

                            $list_invoice[] = [
                                'parent_invoice_id' => $cleanId,
                                'nama_jamaah' => $value2['Contact']['Name'],
                                'no_invoice' => $value['InvoiceNumber'],
                                'line_item_id' => $value3['LineItemID'],
                                'detail_amount_tiap_item' => $value3['LineAmount'],
                                'qty_tiap_item' => $value3['Quantity'],
                                'tanggal' => $value2['DateString'],
                                'tanggal_due_date' => $value2['DueDateString'] ?? null,
                                'paket_name' => $value3['Item']['Name'],
                                'amount_paid' => $value2['AmountPaid'],
                                'total' => $value2['Total'],
                                'status' => $value['Status'],
                                'payment'=>$value['Payments']
                            ];
                        }

                    }

                }
            }
            // dd($list_invoice);
            return response()->json($list_invoice ?: ['message' => 'Xero API Error'], count($list_invoice) > 0 ? $response->status() : 404);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Proxy Error: ' . $e->getMessage()], $e->getCode());
        }
    }

    //status draft
    public function getAllInvoices(Request $request)
    {
        $updatedCount = 0;
        $tenantId = '90a3a97b-3d70-41d3-aa77-586bb1524beb';
        $headers = [
            'Authorization' => 'Bearer ' . env('BARER_TOKEN'),
            'Xero-Tenant-Id' => $tenantId,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        try {
            // ---------------------------------------------------------
            // LANGKAH 1: Ambil Master Data Items (Supaya tidak request berulang)
            // ---------------------------------------------------------
            $itemsResponse = Http::withHeaders($headers)->get('https://api.xero.com/api.xro/2.0/Items');
            if ($itemsResponse->failed()) {
                return response()->json(['error' => 'Gagal ambil data items'], 500);
            }

            $itemPriceMap = [];
            foreach ($itemsResponse->json()['Items'] as $item) {
                // Kita pakai SalesDetails (Harga Jual)
                if (isset($item['SalesDetails']['UnitPrice'])) {
                    $itemPriceMap[$item['Code']] = $item['SalesDetails']['UnitPrice'];
                }
            }

            // ---------------------------------------------------------
            // LANGKAH 2: Ambil Invoice tapi HANYA YANG DRAFT
            // ---------------------------------------------------------
            // Filter Statuses=DRAFT sangat penting agar tidak merusak data keuangan valid
            $invoicesResponse = Http::withHeaders($headers)
                ->get('https://api.xero.com/api.xro/2.0/Invoices');//?Statuses=DRAFT

            if ($invoicesResponse->failed()) {
                return response()->json(['error' => 'Gagal ambil invoices'], 500);
            }

            $invoices = $invoicesResponse->json()['Invoices'];

            // ---------------------------------------------------------
            // LANGKAH 3: Loop Invoice & Cek Apakah Perlu Update
            // ---------------------------------------------------------
            foreach ($invoices as $invoice) {

                $detailResp = Http::withHeaders($headers)
                    ->get("https://api.xero.com/api.xro/2.0/Invoices/" . $invoice['InvoiceID']);

                // Hindari rate limit dengan pause sejenak (opsional, tapi aman)
                // usleep(100000); // 0.1 detik

                if ($detailResp->status() != 200)
                    continue;

                $fullInvoice = $detailResp->json()['Invoices'][0];
                $itemsToUpdate = [];

                foreach ($fullInvoice['LineItems'] as $lineItem) {
                    // Cek apakah baris ini punya ItemCode dan apakah ItemCode ada di Master Data kita
                    if (isset($lineItem['ItemCode']) && isset($itemPriceMap[$lineItem['ItemCode']])) {

                        $currentMasterPrice = $itemPriceMap[$lineItem['ItemCode']];


                        $itemsToUpdate[] = [
                            "LineItemID" => $lineItem['LineItemID'],
                            "ItemCode" => $lineItem['ItemCode'],
                            "Description" => $lineItem['Description'],
                            "UnitAmount" => $currentMasterPrice, // Update ke harga baru
                            "Quantity" => $lineItem['Quantity']  // Qty tetap
                            // Description update opsional
                        ];

                    }
                }

                // ---------------------------------------------------------
                // LANGKAH 4: Eksekusi Update (Jika ada item yang harganya beda)
                // ---------------------------------------------------------
                if (count($itemsToUpdate) > 0) {
                    $payload = [
                        "InvoiceID" => $fullInvoice['InvoiceID'],
                        "LineItems" => $itemsToUpdate
                    ];

                    Http::withHeaders($headers)
                        ->post('https://api.xero.com/api.xro/2.0/Invoices', $payload);

                    $updatedCount++;
                }
            }

            return response()->json([
                'message' => 'Proses selesai',
                'invoices_checked' => count($invoices),
                'invoices_updated' => $updatedCount
            ]);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }


    //status all
    public function getAllInvoicesAll(Request $request)
    {
        $updatedCount = 0;
        $tenantId = '90a3a97b-3d70-41d3-aa77-586bb1524beb';
        $headers = [
            'Authorization' => 'Bearer ' . env('BARER_TOKEN'),
            'Xero-Tenant-Id' => $tenantId,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        try {
            // ---------------------------------------------------------
            // LANGKAH 1: Ambil Master Data Items (Supaya tidak request berulang)
            // ---------------------------------------------------------
            $itemsResponse = Http::withHeaders($headers)->get('https://api.xero.com/api.xro/2.0/Items');
            if ($itemsResponse->failed()) {
                return response()->json(['error' => 'Gagal ambil data items'], 500);
            }

            $itemPriceMap = [];
            foreach ($itemsResponse->json()['Items'] as $item) {
                // Kita pakai SalesDetails (Harga Jual)
                if (isset($item['SalesDetails']['UnitPrice'])) {
                    $itemPriceMap[$item['Code']] = $item['SalesDetails']['UnitPrice'];
                }
            }

            // ---------------------------------------------------------
            // LANGKAH 2: Ambil Invoice tapi HANYA YANG DRAFT
            // ---------------------------------------------------------
            // Filter Statuses=DRAFT sangat penting agar tidak merusak data keuangan valid
            $invoicesResponse = Http::withHeaders($headers)
                ->get('https://api.xero.com/api.xro/2.0/Invoices');//?Statuses=DRAFT

            if ($invoicesResponse->failed()) {
                return response()->json(['error' => 'Gagal ambil invoices'], 500);
            }

            $invoices = $invoicesResponse->json()['Invoices'];

            // ---------------------------------------------------------
            // LANGKAH 3: Loop Invoice & Cek Apakah Perlu Update
            // ---------------------------------------------------------
            foreach ($invoices as $invoice) {

                $detailResp = Http::withHeaders($headers)
                    ->get("https://api.xero.com/api.xro/2.0/Invoices/" . $invoice['InvoiceID']);

                // Hindari rate limit dengan pause sejenak (opsional, tapi aman)
                // usleep(100000); // 0.1 detik

                if ($detailResp->status() != 200)
                    continue;

                $fullInvoice = $detailResp->json()['Invoices'][0];

                $itemsToUpdate = [];

                foreach ($fullInvoice['LineItems'] as $lineItem) {
                    // Cek apakah baris ini punya ItemCode dan apakah ItemCode ada di Master Data kita
                    if (isset($lineItem['ItemCode']) && isset($itemPriceMap[$lineItem['ItemCode']])) {

                        $currentMasterPrice = $itemPriceMap[$lineItem['ItemCode']];


                        $itemsToUpdate[] = [
                            "LineItemID" => $lineItem['LineItemID'],
                            "ItemCode" => $lineItem['ItemCode'],
                            "Description" => $lineItem['Description'],
                            "UnitAmount" => $currentMasterPrice, // Update ke harga baru
                            "Quantity" => $lineItem['Quantity']  // Qty tetap
                            // Description update opsional
                        ];

                    }
                }

                // ---------------------------------------------------------
                // LANGKAH 4: Eksekusi Update (Jika ada item yang harganya beda)
                // ---------------------------------------------------------
                if (count($itemsToUpdate) > 0) {
                    $payload = [
                        "InvoiceID" => $fullInvoice['InvoiceID'],
                        "LineItems" => $itemsToUpdate
                    ];

                    Http::withHeaders($headers)
                        ->post('https://api.xero.com/api.xro/2.0/Invoices', $payload);

                    $updatedCount++;
                }
            }

            return response()->json([
                'message' => 'Proses selesai',
                'invoices_checked' => count($invoices),
                'invoices_updated' => $updatedCount
            ]);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

}
