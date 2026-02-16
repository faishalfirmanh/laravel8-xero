<?php

namespace App\Http\Controllers\Xero;

use App\ConfigRefreshXero;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
class PaymentController extends Controller
{

     use ConfigRefreshXero;

     public function getGroupedAccounts()
    {
        $accessToken = env('BARER_TOKEN');
        $tenantId = env('XERO_TENANT_ID');

        try {

            $tokenData = $this->getValidToken();
            if (!$tokenData) {
                return response()->json(['message' => 'Token kosong/invalid. Silakan akses /xero/connect dulu.'], 401);
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $tokenData["access_token"],
                'Xero-Tenant-Id' => $tenantId,
                'Accept' => 'application/json'
                ])->get('https://api.xero.com/api.xro/2.0/Accounts', [
                    'where' => 'Status=="ACTIVE"'
            ]);

            if ($response->failed()) {
                return response()->json(['error' => 'Gagal fetch Accounts'], 500);
            }

            $accounts = $response->json()['Accounts'];

            $grouped = [];


            ksort($accounts);

            return response()->json([
                'Status' => 'OK',
                'GroupedAccounts' => $accounts
            ]);

        } catch (\Throwable $e) {
             return response()->json(['message' => 'Proxy Error: ' . $e->getMessage()], 500);
        }

        // Filter: Status harus ACTIVE
    }

   public function updatePaymentStatus($payment_id, $status = "DELETED")
    {
        $payload = [
            "Status" => $status,
        ];

        $inv = [];
        $update_payment = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('BARER_TOKEN'), // Sebaiknya ganti ke config('xero.token') nanti
            'Xero-Tenant-Id' => '90a3a97b-3d70-41d3-aa77-586bb1524beb',
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->post("https://api.xero.com/api.xro/2.0/Payments/$payment_id", $payload);

        // Cek error handling agar lebih detail di log jika gagal
        if ($update_payment->failed()) {
            return response()->json([
                'error' => true,
                'message' => 'Gagal Void Payment',
                'details' => $update_payment->json()
            ], $update_payment->status());
        }

        return response()->json($update_payment->json(), $update_payment->status());
    }


    public function getDetailInvoice($id_invoice){
         $response_detail = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('BARER_TOKEN'),
            'Xero-Tenant-Id' => '90a3a97b-3d70-41d3-aa77-586bb1524beb',
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->get("https://api.xero.com/api.xro/2.0/Invoices/$id_invoice");

          return response()->json($response_detail->json(), $response_detail->status());
    }

    public function getPaidPayment($idPayment)
    {
        try {
            $tokenData = $this->getValidToken();
            if (!$tokenData) {
                return response()->json(['message' => 'Token kosong/invalid. Silakan akses /xero/connect dulu.'], 401);
            }

            $response_detail = Http::withHeaders([
                'Authorization'  => 'Bearer ' . $tokenData["access_token"],
                'Xero-Tenant-Id' => env("XERO_TENANT_ID"),
                'Content-Type'   => 'application/json',
                'Accept'         => 'application/json',
            ])->get("https://api.xero.com/api.xro/2.0/Payments/$idPayment");

            if ($response_detail->failed()) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Gagal mengambil detail payment dari Xero',
                    'error'   => $response_detail->body() // Tampilkan alasan asli dari Xero
                ], $response_detail->status());
            }

            // 2. DECODE JSON DENGAN AMAN
            $data = $response_detail->json();

            // 3. PASTIKAN KEY 'Payments' BENAR-BENAR ADA SEBELUM DIAMBIL
            if (empty($data['Payments']) || !isset($data['Payments'][0])) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Data payment tidak ditemukan di response Xero.'
                ], 404);
            }

            // 4. EKSTRAKSI DATA (Gunakan helper data_get agar aman dari index tak terdefinisi)
            $paymentInfo = $data['Payments'][0];

            $amount       = $paymentInfo["Amount"] ?? 0;
            $account_code = data_get($paymentInfo, "Account.AccountID", null);
            //$date         = self::xeroDateToPhp($paymentInfo["Date"] ?? '');
            $invoice_id   = data_get($paymentInfo, "Invoice.InvoiceID", null);

            $reference_id = "$invoice_id update harga otomatis xero paid";

            // ACTION DB ANDA
            // self::insertToDb($amount, $account_code, $date, $invoice_id, $reference_id);

            return response()->json($data, 200);

        } catch (\Exception $e) {
            // 5. FIX HTTP STATUS CODE
            // Pastikan kode status valid (di atas 100 dan di bawah 600), jika tidak jadikan 500
            $statusCode = ($e->getCode() >= 100 && $e->getCode() < 600) ? $e->getCode() : 500;

            return response()->json([
                'status'  => false,
                'message' => 'Error sistem saat get detail payment: ' . $e->getMessage()
            ], $statusCode);
        }
    }

     public function getCreditNoteByPaymentId($idPayment)
    {
        try {
            $tokenData = $this->getValidToken();
            if (!$tokenData) {
                return response()->json(['message' => 'Token kosong/invalid. Silakan akses /xero/connect dulu.'], 401);
            }

            $headers = [
                'Authorization'  => 'Bearer ' . $tokenData["access_token"],
                'Xero-Tenant-Id' => env("XERO_TENANT_ID"),
                'Content-Type'   => 'application/json',
                'Accept'         => 'application/json',
            ];

            // ==========================================================
            // LANGKAH 1: Ambil Data Payment untuk mencari CreditNoteID
            // ==========================================================
            $responsePayment = Http::withHeaders($headers)
                ->get("https://api.xero.com/api.xro/2.0/Payments/$idPayment");

            if ($responsePayment->failed()) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Gagal mengambil detail payment dari Xero',
                    'error'   => $responsePayment->json()
                ], $responsePayment->status());
            }

            // Gunakan helper Laravel untuk langsung mengambil object Payment pertama dengan aman
            $paymentData = $responsePayment->json('Payments.0');

            if (!$paymentData) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Data payment tidak ditemukan.'
                ], 404);
            }

            // Cek apakah Payment ini benar-benar terhubung ke Credit Note
            // (Bisa jadi ID Payment ini sebenarnya terhubung ke Invoice, bukan Credit Note)
            if (empty($paymentData['CreditNote'])) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Payment ini tidak terhubung dengan Credit Note. (Mungkin terhubung ke Invoice atau Prepayment).'
                ], 404);
            }

            $creditNoteId = $paymentData['CreditNote']['CreditNoteID'];


            // ==========================================================
            // LANGKAH 2: Ambil Detail Full Credit Note
            // ==========================================================
            $responseCN = Http::withHeaders($headers)
                ->get("https://api.xero.com/api.xro/2.0/CreditNotes/$creditNoteId");

            if ($responseCN->failed()) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Gagal mengambil detail Credit Note dari Xero',
                    'error'   => $responseCN->json()
                ], $responseCN->status());
            }

            $creditNoteData = $responseCN->json('CreditNotes.0');

            // ==========================================================
            // LANGKAH 3: Return Data (Bisa disesuaikan dengan kebutuhan DB Anda)
            // ==========================================================
            return response()->json([
                'status'  => true,
                'message' => 'Berhasil mendapatkan data Credit Note.',
                'data'    => [
                    'payment_info' => [
                        'payment_id'   => $idPayment,
                        'amount_paid'  => $paymentData['Amount'] ?? 0,
                        'date_paid'    => self::xeroDateToPhp($paymentData['Date'] ?? ''),
                    ],
                    'credit_note_info' => [
                        'credit_note_id'     => $creditNoteData['CreditNoteID'],
                        'credit_note_number' => $creditNoteData['CreditNoteNumber'] ?? '-',
                        'contact_name'       => data_get($creditNoteData, 'Contact.Name', 'Unknown'),
                        'total'              => $creditNoteData['Total'] ?? 0,
                        'remaining_credit'   => $creditNoteData['RemainingCredit'] ?? 0,
                        'status'             => $creditNoteData['Status'] ?? 'UNKNOWN'
                    ],
                    'raw_credit_note' => $creditNoteData // Hapus baris ini jika respon terlalu panjang
                ]
            ], 200);

        } catch (\Exception $e) {
            $statusCode = ($e->getCode() >= 100 && $e->getCode() < 600) ? $e->getCode() : 500;
            return response()->json([
                'status'  => false,
                'message' => 'Error sistem saat get Credit Note: ' . $e->getMessage()
            ], $statusCode);
        }
    }

     public function getPrepaymentByPaymentId($idPayment)
    {
        try {
            // 1. Validasi Token
            $tokenData = $this->getValidToken();
            if (!$tokenData) {
                return response()->json(['message' => 'Token kosong/invalid. Silakan akses /xero/connect dulu.'], 401);
            }

            $headers = [
                'Authorization'  => 'Bearer ' . $tokenData["access_token"],
                'Xero-Tenant-Id' => env("XERO_TENANT_ID"),
                'Content-Type'   => 'application/json',
                'Accept'         => 'application/json',
            ];

            // ==========================================================
            // LANGKAH 1: Ambil Data Payment untuk mencari PrepaymentID
            // ==========================================================
            $responsePayment = Http::withHeaders($headers)
                ->get("https://api.xero.com/api.xro/2.0/Payments/$idPayment");

            if ($responsePayment->failed()) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Gagal mengambil detail payment dari Xero',
                    'error'   => $responsePayment->json()
                ], $responsePayment->status());
            }

            // Ekstrak index ke-0 dengan aman
            $paymentData = $responsePayment->json('Payments.0');

            if (!$paymentData) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Data payment tidak ditemukan.'
                ], 404);
            }

            // PASTIKAN ini adalah pembayaran untuk Prepayment
            if (empty($paymentData['Prepayment'])) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Payment ini tidak terhubung dengan Prepayment. (Mungkin terhubung ke Invoice atau Credit Note).'
                ], 404);
            }

            // Ambil ID Prepayment-nya
            $prepaymentId = $paymentData['Prepayment']['PrepaymentID'];


            // ==========================================================
            // LANGKAH 2: Ambil Detail Full Prepayment
            // ==========================================================
            $responsePrepayment = Http::withHeaders($headers)
                ->get("https://api.xero.com/api.xro/2.0/Prepayments/$prepaymentId");

            if ($responsePrepayment->failed()) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Gagal mengambil detail Prepayment dari Xero',
                    'error'   => $responsePrepayment->json()
                ], $responsePrepayment->status());
            }

            // Ekstrak index ke-0 dari array Prepayments
            $prepaymentData = $responsePrepayment->json('Prepayments.0');

            // ==========================================================
            // LANGKAH 3: Return Data Terformat
            // ==========================================================
            return response()->json([
                'status'  => true,
                'message' => 'Berhasil mendapatkan data Prepayment.',
                'data'    => [
                    'payment_info' => [
                        'payment_id'   => $idPayment,
                        'amount_paid'  => $paymentData['Amount'] ?? 0,
                        'date_paid'    => self::xeroDateToPhp($paymentData['Date'] ?? ''),
                    ],
                    'prepayment_info' => [
                        'prepayment_id'   => $prepaymentData['PrepaymentID'],
                        'reference'       => $prepaymentData['Reference'] ?? '-',
                        'contact_name'    => data_get($prepaymentData, 'Contact.Name', 'Unknown'),
                        'total_amount'    => $prepaymentData['Total'] ?? 0,
                        'remaining_credit'=> $prepaymentData['RemainingCredit'] ?? 0, // Sisa DP yang belum dialokasikan ke Invoice
                        'status'          => $prepaymentData['Status'] ?? 'UNKNOWN'
                    ],
                    'raw_prepayment' => $prepaymentData // Opsional: Hapus jika tidak perlu data mentah
                ]
            ], 200);

        } catch (\Exception $e) {
            $statusCode = ($e->getCode() >= 100 && $e->getCode() < 600) ? $e->getCode() : 500;
            return response()->json([
                'status'  => false,
                'message' => 'Error sistem saat get Prepayment: ' . $e->getMessage()
            ], $statusCode);
        }
    }


}
