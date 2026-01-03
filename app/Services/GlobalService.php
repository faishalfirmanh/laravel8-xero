<?php

namespace App\Services;

use App\Models\InvoicePriceGap;
use App\Models\PaymentsHistoryFix;
use DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
class GlobalService
{

    public function SavedInvoiceValue(
                $uuid_invoice,
                $invNumber,
                $contact_name,
                $total_xero,
                $total_local,
                $payment_return = 0)
    {
        DB::beginTransaction();
        try {
            InvoicePriceGap::updateOrCreate(
                ['invoice_uuid' => $uuid_invoice],
                [
                    'invoice_number' => $invNumber,
                    'contact_name' => $contact_name,
                    'total_nominal_payment_xero' => $total_xero,
                    'total_nominal_payment_local' => $total_local,
                    'total_price_return'=>$payment_return
                ]
            );
            DB::commit();
            Log::info("service SavedInvoiceValue berhasil tambah ke DB");
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error("Gagal SavedInvoiceValue $invNumber: " . $th->getMessage());
        }
    }


    public function getTotalLocalPaymentByuuidInvoice($uuid)
    {
        $total = PaymentsHistoryFix::where('invoice_uuid',$uuid)->sum('amount');
        return $total;
    }

    public function hitungSelisih($angka1, $angka2, $presisi = 2) {
        // Pastikan input jadi string
        $str1 = (string)$angka1;
        $str2 = (string)$angka2;

        // Hitung pengurangan
        $hasil = bcsub($str1, $str2, $presisi);

        // Jika minus, kalikan dengan -1 agar jadi positif (absolute)
        if (bccomp($hasil, '0', $presisi) < 0) {
            $hasil = bcmul($hasil, '-1', $presisi);
        }

        return $hasil;
    }

}
