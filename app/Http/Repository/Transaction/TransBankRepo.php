<?php
namespace App\Http\Repository\Transaction;

use App\Http\Repository\BaseRepository;


use App\Models\Transaction\TransactionNominalBankAccount;

class TransBankRepo extends BaseRepository
{
    public function __construct(TransactionNominalBankAccount $model)
    {
        $this->model = $model;
    }

    public function wherenDataIn($column, $value)
    {
        return $this->model->whereIn($column, $value);
    }

    public function searchData($where = array(), $per_page = 10, $offset = 1, $search_column = "", $keyword = "", $modelWith = [], $whereNull = [])
    {
        $query = $this->model->with($modelWith)->where($where);

        foreach ($whereNull as $col) {
            $query->whereNull($col);
        }

        return $query
            ->whereRaw("LOWER($search_column) like ?", ['%' . strtolower($keyword) . '%'])
            ->paginate($per_page);
    }

    public function getAllDataWithDefault($where = array(), $per_page = 10, $offset = 1, $sort_column = 'id', $sort_order = "ASC", $modelWith = [], $whereNull = [])
    {
        $query = $this->model->with($modelWith)->where($where);

        foreach ($whereNull as $col) {
            $query->whereNull($col);
        }

        return $query->orderBy($sort_column, $sort_order)->paginate($per_page);
    }

    // TransBankRepo
    public function sumPaidBaseByInvoice(int $invoiceId): float
    {
        $total = 0;

        foreach ($this->whereData(['id_parent_invoice' => $invoiceId])->get() as $p) {

            // pembayaran biasa (uang masuk bank)
            if ((float) $p->total_base_receive > 0) {
                $total += (float) $p->total_base_receive;
                continue;
            }

            // pembayaran via apply credit: overpay_id terisi, nilainya ada di sisi spend.
            // baris refund (id_fee_overpay terisi) sengaja tidak dihitung.
            if (!empty($p->overpay_id) && empty($p->id_fee_overpay)) {
                $total += (float) $p->total_base_spend;
                continue;
            }

            // data lama sebelum ada kolom base
            if (!empty($p->nominal_currency) && !empty($p->nominal_receive)) {
                $total += (float) $p->nominal_receive * (float) $p->nominal_currency;
            }
        }

        return $total;
    }

    public function invoiceIdsPaidWithOverpay(): array
    {
        return $this->model
            ->whereNotNull('overpay_id')
            ->whereNull('id_fee_overpay')
            ->whereNotNull('id_parent_invoice')
            ->distinct()
            ->pluck('id_parent_invoice')
            ->toArray();
    }
}
