<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\PaymentParams;
class InvoicesDuplicateControllerOld extends Controller
{
//oldbisa
     function xeroDateToPhp($xeroDate, $format = 'Y-m-d') {
        if (empty($xeroDate)) return null;
        preg_match('/\/Date\((-?\d+)/', $xeroDate, $matches);

        if (!isset($matches[1])) return null;
        return date($format, $matches[1] / 1000);
    }
    public function updateInvoiceSelected(Request $request)
    {
        $rawContent = $request->getContent();
        $data = json_decode($rawContent, true);
        $tot = 0;
        $array = [];
        $errors = [];

        //dd($data["account_id_item"]);

        foreach ($data['items'] as $key => $value) {//ambil selected item
            try {
                // 1. Jika Invoice sudah ada pembayaran (Paid/Partial), Backup & Void dulu
               // dd($value);
                if ($value['no_payment'] != "kosong") {
                    self::getDetailPayment($value['no_payment'],$data["account_id_item"]);
                    // Void payment di Xero (Buka Gembok)
                    if($value["status"] == "PAID"){
                      self::updateInvoicePaidPerRows($value['no_payment']);
                    }

                }

                // 2. Update Item/Harga Invoice
                self::updateInvoicePerRows($value['parentId'], $data['price_update'], $value['lineItemId'], $value['status']);

                // 3. Jika tadi Paid, bayar ulang (Re-Payment)
                //dd($value['no_payment']);
                if ($value['no_payment'] != "kosong") {
                    //dd("create");
                    self::createPayments($value['parentId']);
                    self::deletedRowInvoiceId($value['no_payment']);
                }

                $tot++;
                $array[] = [
                    'no_invoice' => $value['no_invoice'],
                    'status' => 'Success'
                ];

            } catch (\Exception $e) {
                // Tangkap error per item agar loop tidak berhenti total
                $errors[] = [
                    'no_invoice' => $value['no_invoice'],
                    'message' => $e->getMessage()
                ];
            }
        }

        if (count($errors) > 0) {
            return response()->json(['success' => $array, 'errors' => $errors], 207); // 207 Multi-Status
        }

        return response()->json($array, 200);
    }


    public function deletedRowInvoiceId($paymentsId)
    {
        $find =  PaymentParams::where('payments_id',$paymentsId)->first();
        if($find){
            $find->delete();
        }
    }

    public function getDetailPayment($idPayment,$sales_acount)//account_id_item = sales account on items
    {
        // Jangan pakai Try-Catch di sini agar error naik ke fungsi utama
        $response_detail = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('BARER_TOKEN'),
            'Xero-Tenant-Id' => '90a3a97b-3d70-41d3-aa77-586bb1524beb',
            'Accept' => 'application/json',
        ])->get("https://api.xero.com/api.xro/2.0/Payments/$idPayment");

        if ($response_detail->failed()) {
            throw new \Exception("Gagal Get Detail Payment: " . $response_detail->body());
        }

        $data = $response_detail->json();

        // Validasi apakah array Payments ada isinya
        if (empty($data['Payments'])) {
             throw new \Exception("Data Payment kosong dari Xero");
        }

        $payment = $data['Payments'][0];

        $amount = $payment["Amount"];
        $account_code =$sales_acount; //$payment["Account"]["AccountID"] ?? $payment["Account"]["Code"];
        $date = self::xeroDateToPhp($payment["Date"]);
        $invoice_id = $payment["Invoice"]["InvoiceID"];
        $reference_id = "$invoice_id update harga otomatis xero paid";

        self::insertToDb($amount, $account_code, $date, $invoice_id, $reference_id,$idPayment);
    }

    public function insertToDb($amount, $account_code, $date, $invoice_id, $reference_id,$idPayment)
    {
        // Pakai updateOrCreate agar tidak duplikat jika dijalankan 2x
        PaymentParams::updateOrCreate(
            ['invoice_id' => $invoice_id],
            [
                'account_code' => $account_code,
                'date' => $date, // Pastikan kolom di DB tipe date/string yang sesuai
                'amount' => $amount,
                'reference' => $reference_id,
                'payments_id'=>$idPayment
            ]
        );
    }

    public function createPayments($invoice_id)
    {
        $invoice_table = PaymentParams::where('invoice_id', $invoice_id)->first();
       // dd($invoice_table);
        if (!$invoice_table) {
            throw new \Exception("Data backup payment tidak ditemukan di DB lokal untuk Invoice ID: $invoice_id");
        }

        $form = [
            "Payments" => [
                [
                    "Invoice" => [
                        "InvoiceID" => $invoice_id
                    ],
                    "Account" => [
                        "Code" => 200,//$invoice_table->account_code
                    ],
                    "Date" => $invoice_table->date,
                    "Amount" => (float)$invoice_table->amount, // Cast ke float/number
                    "Reference" => $invoice_table->reference ?? "Payment via API",
                ]
            ]
        ];

        // HAPUS SPASI di depan URL
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('BARER_TOKEN'),
            'Xero-Tenant-Id' => '90a3a97b-3d70-41d3-aa77-586bb1524beb',
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->post('https://api.xero.com/api.xro/2.0/Payments', $form);

        if ($response->failed()) {
            throw new \Exception("Gagal Create Payment Baru: " . $response->body());
        }
    }


    public function updateInvoicePaidPerRows($payment_id)
    {
        // PERBAIKAN: Ganti DELETED menjadi VOIDED
        $statusPayload = ["Status" => "DELETED"];

        $update_payment = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('BARER_TOKEN'),
            'Xero-Tenant-Id' => '90a3a97b-3d70-41d3-aa77-586bb1524beb',
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->post("https://api.xero.com/api.xro/2.0/Payments/$payment_id", $statusPayload);

        if ($update_payment->failed()) {
            throw new \Exception("Gagal Void Payment: " . $update_payment->body());
        }
    }

    public function updateInvoicePerRows($parent_id, $amount_input, $line_item_id, $status_invoice)
    {
        $cleanId = str_replace('"', '', $parent_id);

        $response_detail = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('BARER_TOKEN'),
            'Xero-Tenant-Id' => '90a3a97b-3d70-41d3-aa77-586bb1524beb',
            'Accept' => 'application/json',
        ])->get("https://api.xero.com/api.xro/2.0/Invoices/$cleanId");

        if ($response_detail->failed()) {
             throw new \Exception("Gagal Get Invoice Detail: " . $response_detail->body());
        }

        $data = $response_detail->json();
        $all_itemm = [];

        foreach ($data['Invoices'] as $value) {
            foreach ($value['LineItems'] as $value2) {

                // Logika Cek Update
                $cek_is_update_Amount = ($value2['LineItemID'] == $line_item_id) ? $amount_input : $value2['UnitAmount'];

                $itemPayload = [
                    'LineItemID' => $value2['LineItemID'],
                    'Description' => isset($value2['Item']['Name']) ? 'update harga paket ' . $value2['Item']['Name'] : $value2['Description'],
                    'UnitAmount' => $cek_is_update_Amount,
                    'Quantity' => $value2['Quantity'],
                    // 'AccountCode' => '200', // Kadang perlu AccountCode eksplisit
                ];

                // Tambahkan field opsional jika ada
                if (isset($value2['ItemCode'])) $itemPayload['ItemCode'] = $value2['ItemCode'];
                if (isset($value2['AccountID'])) $itemPayload['AccountID'] = $value2['AccountID'];
                if (isset($value2['TaxType'])) $itemPayload['TaxType'] = $value2['TaxType'];

                $all_itemm[] = $itemPayload;
            }
        }

        $parent_wrap = ['InvoiceID' => $parent_id, 'LineItems' => $all_itemm];

        $update_tiap_row_invoices = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('BARER_TOKEN'),
            'Xero-Tenant-Id' => '90a3a97b-3d70-41d3-aa77-586bb1524beb',
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->post('https://api.xero.com/api.xro/2.0/Invoices', $parent_wrap);

        if ($update_tiap_row_invoices->failed()) {
             throw new \Exception("Gagal Update Invoice: " . $update_tiap_row_invoices->body());
        }
    }
}
