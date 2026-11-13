<?php

namespace App\Traits;

use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Helper bersama laporan pembayaran (klasifikasi pembayaran, nomor WA, tanggal, CSV).
 * CoaController (laporan COA) boleh dipindah memakai trait ini nanti; sementara tidak perlu disentuh.
 */
trait PaymentReportHelper
{
    protected function reportDateRange(Request $request): array
    {
        $today = Carbon::today()->toDateString();
        $from = $request->filled('date_from') ? Carbon::parse($request->date_from)->toDateString() : $today;
        $to = $request->filled('date_to') ? Carbon::parse($request->date_to)->toDateString() : $from;

        return [$from, $to];
    }

    /** Array atau "1,2,3" -> array bersih tanpa elemen kosong. */
    protected function csvToList($value): array
    {
        $list = is_array($value) ? $value : explode(',', (string) $value);

        return array_values(array_filter(array_map('trim', $list), function ($v) {
            return $v !== '';
        }));
    }

    /**
     * Jenis pembayaran (status invoice dihitung dari SEMUA pembayaran invoice, urut date_transaction, id):
     *  dp             : pembayaran pertama invoice, belum lunas        (closing / dp masuk)
     *  lunas_langsung : pembayaran pertama langsung melunasi            (closing / dp masuk, "closing-lunas")
     *  pelunasan      : pembayaran ke-2 dst yang membuat invoice lunas  (baru pelunasan)
     *  cicilan        : pembayaran ke-2 dst yang belum melunasi         (tidak tampil di 3 filter)
     */
    protected function classifyPaymentRow($r)
    {
        $eps = 0.01;
        $total = (float) $r->invoice_total;
        $paidAfter = (float) $r->paid_after;
        $paidBefore = $paidAfter - (float) $r->amount;

        $lunasNow = $total > 0 && $paidAfter >= $total - $eps && $paidBefore < $total - $eps;
        $isFirst = (int) $r->pay_no === 1;

        if ($isFirst && $lunasNow) {
            $kind = 'lunas_langsung';
        } elseif ($isFirst) {
            $kind = 'dp';
        } elseif ($lunasNow) {
            $kind = 'pelunasan';
        } else {
            $kind = 'cicilan';
        }

        $r->kind = $kind;
        $r->sisa = max($total - $paidAfter, 0);
        $r->via_overpay = !is_null($r->overpay_id);
        $r->mixed = (int) $r->other_lines > 0;
        $r->wa_number = $this->normalizeWaNumber($r->phone_number);
        unset($r->phone_number);

        return $r;
    }

    /** Nomor untuk wa.me: hanya digit, kode negara 62. Null kalau kosong / tidak valid (tombol tidak tampil). */
    protected function normalizeWaNumber($phone)
    {
        $first = preg_split('/[,;\/]/', (string) $phone)[0]; // kalau ada beberapa nomor, ambil yang pertama
        $n = preg_replace('/\D+/', '', $first);
        if ($n === '') {
            return null;
        }
        if (strpos($n, '00') === 0) {          // 0062812... -> 62812...
            $n = substr($n, 2);
        } elseif (strpos($n, '620') === 0) {   // 620812... -> 62812...
            $n = '62' . substr($n, 3);
        } elseif (strpos($n, '0') === 0) {     // 0812... -> 62812...
            $n = '62' . substr($n, 1);
        } elseif (strpos($n, '8') === 0) {     // 812... -> 62812...
            $n = '62' . $n;
        }
        $len = strlen($n);

        return ($len >= 10 && $len <= 15) ? $n : null;
    }
}