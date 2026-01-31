<?php

namespace App\Http\Controllers\Transaction;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Services\XeroService;
use GuzzleHttp\Client;

class XeroController extends Controller
{
    public function index()
    {
        return view('admin.xero.list-transaksi');
    }

    public function listTransaksi(Request $request)
    {
        try {
            $accessToken = XeroService::getAccessToken();
            $tenantId = env('XERO_TENANT_ID');

            $client = new Client([
                'base_uri' => 'https://api.xero.com/api.xro/2.0/',
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Xero-tenant-id' => $tenantId,
                    'Accept' => 'application/json',
                ],
            ]);

            $response = $client->get('Invoices');
            $data = json_decode($response->getBody(), true);
            $invoices = $data['Invoices'] ?? [];

            // ✨ Semua status termasuk PAID
            $activeStatuses = ['DRAFT', 'SUBMITTED', 'AUTHORISED', 'PAID'];
            $invoices = array_filter($invoices, fn($inv) => in_array($inv['Status'], $activeStatuses));

            if ($request->contact) {
                $invoices = array_filter($invoices, fn($inv) => str_contains(strtolower($inv['Contact']['Name'] ?? ''), strtolower($request->contact)));
            }
            if ($request->number) {
                $invoices = array_filter($invoices, fn($inv) => str_contains(strtolower($inv['InvoiceNumber'] ?? ''), strtolower($request->number)));
            }
            if ($request->status) {
                $invoices = array_filter($invoices, fn($inv) => $inv['Status'] === $request->status);
            }

            $perPage = 10;
            $page = $request->page ?? 1;
            $total = count($invoices);
            $invoices = array_slice($invoices, ($page-1)*$perPage, $perPage);

            return response()->json([
                'data' => array_values($invoices),
                'total' => $total,
                'perPage' => $perPage,
                'currentPage' => $page,
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ✨ Void Invoice (untuk AUTHORISED & PAID)
    public function voidInvoice($id)
    {
        try {
            $accessToken = XeroService::getAccessToken();
            $tenantId = env('XERO_TENANT_ID');

            $client = new Client([
                'base_uri' => 'https://api.xero.com/api.xro/2.0/',
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Xero-tenant-id' => $tenantId,
                    'Accept' => 'application/json',
                ],
            ]);

            $client->post("Invoices/{$id}/void");

            return response()->json(['message' => 'Invoice berhasil di-void']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ✨ Delete Invoice (untuk DRAFT/SUBMITTED)
    public function deleteInvoice($id)
    {
        try {
            $accessToken = XeroService::getAccessToken();
            $tenantId = env('XERO_TENANT_ID');

            $client = new Client([
                'base_uri' => 'https://api.xero.com/api.xro/2.0/',
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Xero-tenant-id' => $tenantId,
                    'Accept' => 'application/json',
                ],
            ]);

            $client->delete("Invoices/{$id}");

            return response()->json(['message' => 'Invoice berhasil dihapus']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
