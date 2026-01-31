<?php

namespace App\Http\Controllers\Transaction\Revenue;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Services\XeroService;
use GuzzleHttp\Client;

class XeroController extends Controller
{
    public function index()
    {
        return view('xero.list-transaksi');
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

            // ❌ Hanya tampilkan yang aktif: DRAFT, SUBMITTED, AUTHORISED
            $activeStatuses = ['DRAFT', 'SUBMITTED', 'AUTHORISED'];
            $invoices = array_filter($invoices, fn($inv) => in_array($inv['Status'], $activeStatuses));

            // 🔎 Filter search (opsional)
            if ($request->contact) {
                $invoices = array_filter($invoices, fn($inv) => str_contains(strtolower($inv['Contact']['Name'] ?? ''), strtolower($request->contact)));
            }
            if ($request->number) {
                $invoices = array_filter($invoices, fn($inv) => str_contains(strtolower($inv['InvoiceNumber'] ?? ''), strtolower($request->number)));
            }
            if ($request->status) {
                $invoices = array_filter($invoices, fn($inv) => $inv['Status'] === $request->status);
            }

            // Pagination sederhana
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
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ✨ VIEW DETAIL INVOICE
    public function viewInvoice($id)
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

            $response = $client->get("Invoices/{$id}");
            $data = json_decode($response->getBody(), true);
            $invoice = $data['Invoices'][0] ?? null;

            return response()->json($invoice);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ✨ CREATE INVOICE
    public function createInvoice(Request $request)
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

            $response = $client->post('Invoices', [
                'json' => $request->all()
            ]);

            return response()->json(json_decode($response->getBody(), true));
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ✨ UPDATE INVOICE
    public function updateInvoice(Request $request, $id)
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

            $response = $client->post("Invoices/{$id}", [
                'json' => $request->all()
            ]);

            return response()->json(json_decode($response->getBody(), true));
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
