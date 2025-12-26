<?php

namespace App\Http\Controllers;

use App\Servics\ConfigXero;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
use App\ConfigRefreshXero;
class TaxRateController extends Controller
{

     use ConfigRefreshXero;

    public function getTaxRate(Request $request)
    {
        try {
            $tokenData = $this->getValidToken();
            if (!$tokenData) {
                return response()->json(['message' => 'Token kosong/invalid. Silakan akses /xero/connect dulu.'], 401);
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $tokenData["access_token"],
                'Xero-Tenant-Id' => env('XERO_TENANT_ID'),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',])
            ->get('https://api.xero.com/api.xro/2.0/TaxRates', [
                'where' => 'Status=="ACTIVE"'
            ]);
            // 3. Return JSON ke Frontend
            return response()->json($response->json());

        } catch (\Throwable $th) {
             return response()->json(['message' => 'Proxy Error: ' . $e->getMessage()], 500);
        }

    }

}
