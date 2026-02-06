<?php

namespace App\Http\Controllers\Transaction\Revenue;
use App\Http\Controllers\Controller;
use App\ConfigRefreshXero;
use Illuminate\Http\Request;
use GuzzleHttp\Client;

class XeroTransaksiController extends Controller
{
    use ConfigRefreshXero;

    /**
     * View
     */
    public function index()
    {
        return view('xero.list-transaksi');
    }

    /**
     * LIST TRANSAKSI + FILTER + PAGINATION
     */
    public function listTransaksi(Request $request)
    {
        try {
            // 🔑 Ambil token valid (auto refresh)
            $tokens = $this->getValidToken();

            if (!$tokens || empty($tokens['access_token'])) {
                return response()->json([
                    'error' => 'Token Xero tidak valid, silakan login ulang'
                ], 401);
            }

            $accessToken = $tokens['access_token'];

            // 🔑 Ambil tenant ID
            $tenantId = $this->getTenantId($accessToken);
            if (!$tenantId) {
                return response()->json(['error' => 'Tenant ID tidak ditemukan'], 500);
            }

            // 🔥 Client Xero
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

            // ✅ STATUS YANG DITAMPILKAN
            $allowedStatus = ['DRAFT', 'SUBMITTED', 'AUTHORISED', 'PAID'];
            $invoices = array_filter($invoices, fn ($i) =>
                in_array($i['Status'] ?? '', $allowedStatus)
            );

            // 🔎 FILTER
            if ($request->contact) {
                $invoices = array_filter($invoices, fn ($i) =>
                    str_contains(
                        strtolower($i['Contact']['Name'] ?? ''),
                        strtolower($request->contact)
                    )
                );
            }

            if ($request->number) {
                $invoices = array_filter($invoices, fn ($i) =>
                    str_contains(
                        strtolower($i['InvoiceNumber'] ?? ''),
                        strtolower($request->number)
                    )
                );
            }

            if ($request->status) {
                $invoices = array_filter($invoices, fn ($i) =>
                    ($i['Status'] ?? '') === $request->status
                );
            }

            // 📄 PAGINATION MANUAL
            $perPage = 10;
            $page = (int) ($request->page ?? 1);
            $total = count($invoices);

            $invoices = array_slice(
                array_values($invoices),
                ($page - 1) * $perPage,
                $perPage
            );

            return response()->json([
                'data' => $invoices,
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

    /**
     * VOID INVOICE
     * (AUTHORISED & PAID)
     */
public function forceDeleteInvoice($uuid_inv)
{
    try {
        $tokens = $this->getValidToken();
        if (!$tokens) {
            return response()->json(['error' => 'Token tidak valid'], 401);
        }

        $accessToken = $tokens['access_token'];
        $tenantId = $this->getTenantId($accessToken);

        $client = new Client([
            'base_uri' => 'https://api.xero.com/api.xro/2.0/',
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
                'Xero-tenant-id' => $tenantId,
                'Accept' => 'application/json',
            ],
        ]);

        // 1️⃣ Ambil invoice dulu
        $response = $client->get("Invoices/{$uuid_inv}");
        $invoice = json_decode($response->getBody(), true)['Invoices'][0];

        $status = $invoice['Status'];

        // 2️⃣ Tentukan aksi
        if (in_array($status, ['AUTHORISED', 'PAID'])) {
            // VOID
            $client->post("Invoices/{$uuid_inv}/void");

            return response()->json([
                'message' => 'Invoice berhasil di-void'
            ]);
        }

        if (in_array($status, ['DRAFT', 'SUBMITTED'])) {
            // DELETE
            $client->delete("Invoices/{$uuid_inv}");

            return response()->json([
                'message' => 'Invoice berhasil dihapus'
            ]);
        }

        return response()->json([
            'error' => 'Status invoice tidak bisa dihapus'
        ], 400);

    } catch (\Exception $e) {
        return response()->json([
            'error' => $e->getMessage()
        ], 500);
    }
}
}