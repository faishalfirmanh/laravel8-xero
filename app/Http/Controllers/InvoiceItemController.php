<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use App\Http\Controllers\InvoicesDuplicateController;
class InvoiceItemController extends Controller
{
private $xeroBaseUrl = 'https://api.xero.com/api.xro/2.0/Invoices';

    private function getHeaders()
    {
        // Ganti ini dengan logic pengambilan token Xero Anda
        $accessToken = env();
        $tenantId = session('xero_tenant_id');

        return [
            'Authorization' => 'Bearer ' .env('BARER_TOKEN'),
            'Xero-Tenant-Id' => env('XERO_TENANT_ID'),
            'Accept' => 'application/json',
            'Content-Type' => 'application/json'
        ];
    }

    public function savePaid($payment_id)
    {
         $inject_inv = new InvoicesDuplicateController();
         $inject_inv->getDetailPayment($payment_id);
         $inject_inv->updateInvoicePaidPerRows($payment_id);
         sleep(2);
    }

   public function saveItem(Request $request)
{
    // 1. Validasi Input
    $request->validate([
        'invoice_id'   => 'required|string',
        'item_code'    => 'required|string',
        'qty'          => 'required|numeric',
        'price'        => 'required|numeric',
        'disc_amount'  => 'nullable|numeric',
        // Tambahkan validasi untuk Agent dan Divisi
        'agent_id'     => 'nullable|string',
        'divisi_id'    => 'nullable|string',
        'status_invoice'=> 'required|string',
    ]);

    // Bersihkan ID Invoice
    $invoiceId = preg_replace('/[^a-zA-Z0-9-]/', '', $request->invoice_id);

    // 2. Ambil Data Invoice dari Xero
    $response = Http::withHeaders([
        'Authorization' => 'Bearer ' . env('BARER_TOKEN'),
        'Xero-Tenant-Id' => env('XERO_TENANT_ID'),
        'Content-Type' => 'application/json',
        'Accept' => 'application/json',
    ])->get($this->xeroBaseUrl . '/' . $invoiceId);

    if ($response->failed()) {
        return response()->json(['status' => 'error', 'message' => 'Gagal koneksi ke Xero'], 500);
    }

    $invoiceData = $response->json()['Invoices'][0];
    $currentLineItems = $invoiceData['LineItems'] ?? [];

    // 3. Hitung Diskon (Nominal -> Persen)
    $subtotal = $request->qty * $request->price;
    $discountRate = 0;
    if ($subtotal > 0 && $request->disc_amount > 0) {
        $discountRate = ($request->disc_amount / $subtotal) * 100;
    }

    // --- [FIX UTAMA] SUSUN ARRAY TRACKING ---
    $tracking = [];

    // Kategori 1: Agent (Pastikan "Name" sesuai persis dengan di Xero)
    if ($request->filled('agent_id')) {
        $tracking[] = [
            'Name' => 'Agent', // Ganti dengan 'Agen' jika di Xero namanya bahasa Indonesia
            'Option' => '',
            'TrackingOptionID' => $request->agent_id
        ];
    }

    // Kategori 2: Divisi
    if ($request->filled('divisi_id')) {
        $tracking[] = [
            'Name' => 'Divisi', // Ganti dengan 'Division' jika di Xero bahasa Inggris
            'Option' => '',
            'TrackingOptionID' => $request->divisi_id
        ];
    }

    // 4. Siapkan Line Item Baru
    $newLineItem = [
        'ItemCode'      => $request->item_code,
        'Description'   => $request->description,
        'Quantity'      => $request->qty,
        'UnitAmount'    => $request->price,
        'DiscountRate'  => round($discountRate, 4),
        'AccountCode'   => $request->account_code ?? '200',
        'TaxType'       => $request->tax_type ?? 'NONE',
        'Tracking'      => $tracking // <--- MASUKKAN TRACKING DISINI
    ];

    if ($request->filled('line_item_id')) {
        $newLineItem['LineItemID'] = $request->line_item_id;
    }

    // 5. Update Array LineItems
    $updatedLineItems = [];
    $found = false;

    foreach ($currentLineItems as $item) {
        // Cek apakah ini baris yang sedang diedit
        if ($request->filled('line_item_id') && isset($item['LineItemID']) && $item['LineItemID'] == $request->line_item_id) {
            $updatedLineItems[] = $newLineItem;
            $found = true;
        } else {
            // --- [PENTING] PERTAHANKAN DATA LAMA ---
            // Kita harus menyalin Tracking lama juga, jika tidak, baris lain akan kehilangan trackingnya
            $updatedLineItems[] = [
                'LineItemID' => $item['LineItemID'],
                'Quantity'   => $item['Quantity'],
                'UnitAmount' => $item['UnitAmount'],
                'ItemCode'   => $item['ItemCode'] ?? null,
                'Description'=> $item['Description'] ?? null,
                'AccountCode'=> $item['AccountCode'] ?? null,
                'TaxType'    => $item['TaxType'] ?? null,
                'DiscountRate'=> $item['DiscountRate'] ?? 0,
                'Tracking'   => $item['Tracking'] ?? [] // <--- JANGAN LUPA INI
            ];
        }
    }

    // Jika Create Baru
    if (!$found && empty($request->line_item_id)) {
        $updatedLineItems[] = $newLineItem;
    }

    // 6. Kirim ke Xero
    $payload = [
        'InvoiceID' => $invoiceId,
        'LineItems' => $updatedLineItems
    ];

    $updateResponse = Http::withHeaders([
        'Authorization' => 'Bearer ' . env('BARER_TOKEN'),
        'Xero-Tenant-Id' => env('XERO_TENANT_ID'),
        'Content-Type' => 'application/json',
        'Accept' => 'application/json',
    ])->post($this->xeroBaseUrl . '/' . $invoiceId, $payload);

    if ($updateResponse->successful()) {
        $updatedInvoice = $updateResponse->json()['Invoices'][0];
        return response()->json([
            'status' => 'success',
            'message' => 'Invoice updated successfully',
            'data' => $updatedInvoice
        ]);
    }

    return response()->json([
        'status' => 'error',
        'message' => 'Xero Error: ' . $updateResponse->body()
    ], 400);
}

    /**
     * DELETE ITEM
     * Di Xero, delete item = Update Invoice tanpa item tersebut.
     */
    public function deleteItem(Request $request, $lineId)
    {
        $invoiceId = $request->invoice_id; // Harus dikirim dari frontend

        // 1. Ambil Data
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('BARER_TOKEN'),
            'Xero-Tenant-Id' => env('XERO_TENANT_ID'),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->get($this->xeroBaseUrl . '/' . $invoiceId);

        if ($response->failed()) return response()->json(['status' => 'error'], 500);

        $invoiceData = $response->json()['Invoices'][0];
        $currentLineItems = $invoiceData['LineItems'] ?? [];

        // 2. Filter Array (Hapus item yang ID nya cocok)
        $newLineItems = [];
        foreach ($currentLineItems as $item) {
            if ($item['LineItemID'] != $lineId) {
                $newLineItems[] = $item; // Masukkan item yang TIDAK dihapus
            }
        }

        // 3. Kirim Update
        $payload = [
            'InvoiceID' => $invoiceId,
            'LineItems' => $newLineItems
        ];

        $updateResponse = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('BARER_TOKEN'),
            'Xero-Tenant-Id' => env('XERO_TENANT_ID'),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])
            ->post($this->xeroBaseUrl . '/' . $invoiceId, $payload);

        if ($updateResponse->successful()) {
            return response()->json(['status' => 'success', 'message' => 'Item deleted from Xero']);
        }

        return response()->json(['status' => 'error', 'message' => 'Failed to delete'], 400);
    }

}
