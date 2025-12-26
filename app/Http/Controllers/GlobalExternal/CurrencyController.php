<?php

namespace App\Http\Controllers\GlobalExternal;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use App\Http\Controllers\Controller;
class CurrencyController extends Controller
{

    public function idrToSar(Request $request)
    {
        // Validasi input
        $request->validate([
            'amount' => 'required|numeric'
        ]);

        $amountRp = $request->input('amount'); // Ambil dari JSON Postman
        $rates = $this->getRates();

        if (!$rates) return response()->json(['error' => 'Gagal ambil rate'], 500);

        $rateIDR = floatval($rates['IDR']);
        $rateSAR = floatval($rates['SAR']);

        $result = ($amountRp / $rateIDR) * $rateSAR;

        return response()->json([
            'from' => 'IDR',
            'to' => 'SAR',
            'input_amount' => $amountRp,
            'converted_result' => round($result, 2)
        ]);
    }


    private function getRates()
    {
        // Cache selama 60 menit agar hemat kuota API dan loading cepat
        return Cache::remember('currency_rates', 60 * 60, function () {
            $apiKey = 'f759b7cefeb24896bc934f6a01c498a1';

            $response = Http::get("https://api.currencyfreaks.com/v2.0/rates/latest", [
                'apikey' => $apiKey,
                'symbols' => 'IDR,SAR,USD'
            ]);

            if ($response->successful()) {
                return $response->json()['rates'];
            }

            return null; // Handle jika error
        });
    }

    public function sarToIdr(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric'
        ]);

        $amountSar = $request->input('amount'); // Ambil dari JSON Postman
        $rates = $this->getRates();

        if (!$rates) return response()->json(['error' => 'Gagal ambil rate'], 500);

        $rateIDR = floatval($rates['IDR']);
        $rateSAR = floatval($rates['SAR']);

        $result = ($amountSar / $rateSAR) * $rateIDR;

        return response()->json([
            'from' => 'SAR',
            'to' => 'IDR',
            'input_amount' => $amountSar,
            'converted_result' => round($result, 0)
        ]);
    }

    public function usdToIdr(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric'
        ]);

        // [Fix 1] Ubah nama variabel jadi USD (sesuai fungsi)
        $amountUsd = $request->input('amount');

        $rates = $this->getRates();

        if (!$rates) return response()->json(['error' => 'Gagal ambil rate'], 500);

        $rateUSD = $rateUSD = floatval($rates['USD'] ?? 1);// Nilainya pasti 1
        $rateIDR = floatval($rates['IDR']); // Contoh: 15800

        // Rumus: (Input USD / Rate USD) * Rate IDR
        // Sebenarnya ($amountUsd * $rateIDR) saja cukup, tapi rumus Anda tetap valid.
        $result = ($amountUsd / $rateUSD) * $rateIDR;

        return response()->json([
            'from' => 'USD',
            'to' => 'IDR',
            'input_amount' => $amountUsd,
            'converted_result' => round($result, 0)
        ]);
    }

    public function idrToUsd(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric'
        ]);


        $amountIdr = $request->input('amount');
        $rates = $this->getRates();

        if (!$rates) return response()->json(['error' => 'Gagal ambil rate'], 500);

        $rateIDR = floatval($rates['IDR']);

        if ($rateIDR <= 0) return response()->json(['error' => 'Rate IDR Invalid'], 500);
        $result = $amountIdr / $rateIDR;

        return response()->json([
            'from' => 'IDR',
            'to' => 'USD',
            'input_amount' => $amountIdr,
            // [Fix 2] USD gunakan 2 desimal (sen)
            'converted_result' => round($result, 2)
        ]);
    }
}
