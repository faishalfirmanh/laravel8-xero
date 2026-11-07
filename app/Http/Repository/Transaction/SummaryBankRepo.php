<?php
namespace App\Http\Repository\Transaction;

use App\Http\Repository\BaseRepository;

use App\Models\Transaction\SummaryNominalBank;
use Illuminate\Support\Facades\DB;


class SummaryBankRepo extends BaseRepository
{
    public function __construct(SummaryNominalBank $model)
    {
        $this->model = $model;
    }

    public function wherenDataIn($column, $value)
    {
        return $this->model->whereIn($column, $value);
    }

    public function updateCreate($req)
    {
        return $this->model->updateOrCreate(
            ['bank_id' => $req['bank_id']],
            $req
        );
    }

    /**
     * Hitung ulang ringkasan SATU bank dari semua transaksinya.
     *
     * Ada dua tipe bank: bank Rupiah dan bank SAR. Ringkasannya memakai mata uang bank itu sendiri,
     * jadi yang dijumlahkan adalah angka aslinya (nominal_receive / nominal_spend), BUKAN total_base_*.
     * Salah memilih bank (mata uang tidak cocok) dianggap kesalahan input user.
     *   masuk = jumlah nominal_receive, keluar = jumlah nominal_spend, sisa = masuk - keluar.
     *
     * Panggil SETELAH baris transaksinya tersimpan/terhapus, di dalam transaksi DB yang sama.
     * Hanya menyentuh baris summary_nominal_banks milik bank ini (dibuat bila belum ada).
     * Return: ['nominal_in' =>, 'nominal_out' =>, 'final_nominal' =>]
     */
    public function recalcFromLedger(int $bankId): array
    {
        $sum = DB::table('transaction_nominal_bank_accounts')
            ->where('uuid_bank', $bankId)
            ->selectRaw('COALESCE(SUM(nominal_receive), 0) AS masuk, COALESCE(SUM(nominal_spend), 0) AS keluar')
            ->first();

        $in = round((float) $sum->masuk, 4);
        $out = round((float) $sum->keluar, 4);
        $values = [
            'nominal_in' => $in,
            'nominal_out' => $out,
            'final_nominal' => round($in - $out, 4),
            'updated_at' => now(),
        ];

        $existing = DB::table('summary_nominal_banks')
            ->where('bank_id', $bankId)
            ->lockForUpdate()
            ->first();

        if ($existing) {
            DB::table('summary_nominal_banks')->where('id', $existing->id)->update($values);
        } else {
            DB::table('summary_nominal_banks')->insert($values + ['bank_id' => $bankId, 'created_at' => now()]);
        }

        return [
            'nominal_in' => $values['nominal_in'],
            'nominal_out' => $values['nominal_out'],
            'final_nominal' => $values['final_nominal'],
        ];
    }

}