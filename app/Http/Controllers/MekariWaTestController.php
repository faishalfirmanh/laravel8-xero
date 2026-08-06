<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Jobs\SendVaNotifMekariJob;

class MekariWaTestController extends Controller
{
    public function sendTest(Request $request)
    {
        $data = $request->validate([
            'phone' => 'required|string',
            'invoice_number' => 'required|string',
            'va_number' => 'required|string',
            'bank_name' => 'nullable|string',
            'paket_name' => 'nullable|string',
            'tot_payment' => 'required|numeric',
            'tot_nominal' => 'required|numeric',
        ]);

        SendVaNotifMekariJob::dispatch(
            $data['phone'],
            $data['invoice_number'],
            $data['va_number'],
            $data['bank_name'] ?? null,
            $data['paket_name'] ?? null,
            (float) $data['tot_payment'],
            (float) $data['tot_nominal']
        );

        return response()->json(['status' => 'queued']);
    }
}