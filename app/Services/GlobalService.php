<?php

namespace App\Services;

use App\Models\InvoicePriceGap;
use App\Models\Xero\XeroRequestUsedLimit;
use App\Models\PaymentsHistoryFix;
use App\Models\Revenue\Hotel\InvoicesHotel;
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


     public function cekJenisPaketBasePagar(string $name) {
        if (strpos($name, '#') !== false) {
            $parts = explode('#', $name);
            if (isset($parts[1])) {
                return $parts[1];
            }
        }else{
            return 1;
        }
    }




    public function generateInvoiceHotel()
    {
        $now = Carbon::now();
        $prefix = 'INV/' . $now->format('Ymd') . '/';
        $lastInvoice = InvoicesHotel::where('no_invoice_hotel', 'like', $prefix . '%')
            ->orderBy('id', 'desc') // Ambil yang paling baru dibuat
            ->first();

        if (!$lastInvoice) {
            $newNumber = 1;
        } else {
            $parts = explode('/', $lastInvoice->no_invoice_hotel);
            $lastNumber = end($parts);
            $newNumber = intval($lastNumber) + 1;
        }
        $resultInvoice = $prefix . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
        return $resultInvoice;
    }

    public function requestCalculationXero(int $available_min, int $avilabe_day ) {
        $used_min = 60 - $available_min;
        $used_day = 5000 - $avilabe_day;
        $day_now = now()->format('Y-m-d');//Carbon::now()->format('Y-m-d');
         XeroRequestUsedLimit::updateOrCreate([
            'tracking_date'=>$day_now,
         ],[
            'total_request_used_min'=>$used_min,
            'total_request_used_day'=>$used_day,
            'available_request_min'=>$available_min,
            'available_request_day'=>$avilabe_day,
            'tracking_date'=>$day_now
         ]);

         return true;
    }

    public function getDataAvailabeRequestXero() {
        $day_now = now()->format('Y-m-d');//Carbon::now()->format('Y-m-d');
        $data = XeroRequestUsedLimit::where( 'tracking_date',$day_now,)->first();
        return $data;
    }

}
