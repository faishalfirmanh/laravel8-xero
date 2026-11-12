<?php

namespace App\Http\Repository\MasterData;

use App\Http\Repository\BaseRepository;
use App\Models\MasterData\Coa;
use DB;

class CoaRepo extends BaseRepository
{
    public function __construct(Coa $model)
    {
        $this->model = $model;
    }

    public function firstCreate($req)
    {

        return $this->model->firstOrCreate(['account_uuid' => $req['account_uuid']], $req);
    }
    // =====================================================================
// A. Tempel di CoaRepo (App\Http\Repository\...)
//    use App\Models\MasterData\Coa;
//    use Illuminate\Support\Facades\DB;
//    Kalau CoaRepo sudah punya method cari-by-code, pakai itu saja.
//    Asumsi: tabel coa punya kolom id, code, name.
// =====================================================================

    public function getByCodes(array $codes)
    {
        return DB::table((new Coa)->getTable())
            ->whereIn('code', $codes)
            ->orderBy('code')
            ->get(['id', 'code', 'name']);
    }


    // =====================================================================
// B. Tempel di TransBankRepo
//    use Illuminate\Support\Facades\DB;
//
//    Butuh window function: MariaDB 10.11 (production) dan MySQL 8 (lokal) sudah mendukung.
// =====================================================================

    /**
     * Semua pembayaran invoice (bukan refund fee_overpay) untuk invoice yang punya
     * minimal 1 baris item di COA $coaId, pada rentang date_transaction.
     *
     * pay_no     : urutan pembayaran per invoice (1 = pembayaran pertama / DP)
     * paid_after : total terbayar per invoice setelah baris ini (dalam mata uang invoice)
     * Window dihitung SEBELUM filter tanggal, jadi pay_no & paid_after selalu dari riwayat penuh.
     *
     * Nominal baris:
     *  - pembayaran biasa : nominal_receive / total_base_receive
     *  - bayar via overpay (overpay_id terisi) : nominal_spend / total_base_spend
     */





    // =====================================================================
// C. Index yang disarankan (migration terpisah, cek dulu apakah sudah ada)
// =====================================================================
// transaction_nominal_bank_accounts : (id_parent_invoice, date_transaction, id)
// item_detail_invoices              : (coa_id, parent_inv_id)

    public function getAllDataWithDefault($where = [], $per_page = 10, $offset = 1, $sort_column, $sort_order = "ASC", $modelWith = [])
    {
        $query = $this->model->with($modelWith);

        if (is_callable($where)) {
            // Handle Closure dari chekWhere()
            $query->where($where);
        } else {
            foreach ($where as $column => $value) {
                if (is_array($value)) {
                    $query->whereIn($column, $value);
                } else {
                    $query->where($column, $value);
                }
            }
        }

        return $query->orderBy($sort_column, $sort_order)
            ->paginate($per_page);
    }

    public function wherenDataIn($column, $list_id)
    {
        $query = $this->model->whereIn($column, $list_id);

        return $query;
    }

    public function searchData($where = array(), $per_page = 10, $offset = 1, $search_column = "", $keyword = "", $modelWith = [])
    {
        $query = $this->model->with($modelWith);

        // Looping untuk mengecek apakah ada multiple values (array) untuk whereIn
        foreach ($where as $column => $value) {
            if (is_array($value)) {
                $query->whereIn($column, $value);
            } else {
                $query->where($column, $value);
            }
        }

        // Menggunakan parameter binding (?) untuk mencegah SQL Injection
        $keywordClean = strtolower($keyword);

        // Hapus offset() dan limit()
        return $query->whereRaw("LOWER({$search_column}) LIKE ?", ['%' . $keywordClean . '%'])
            ->paginate($per_page);
    }

    // =====================================================================
// B. Tempel di TransBankRepo   (use Illuminate\Support\Facades\DB;)
//    Window function: MariaDB 10.11 (production) & MySQL 8 (lokal) sudah mendukung.
// =====================================================================

    /**
     * Semua pembayaran invoice (bukan refund fee_overpay) untuk invoice yang punya
     * minimal 1 baris item di COA $coaId, pada rentang date_transaction.
     *
     * pay_no     : urutan pembayaran per invoice (1 = pembayaran pertama)
     * paid_after : total terbayar per invoice setelah baris ini (mata uang invoice)
     * Window dihitung SEBELUM filter tanggal, jadi pay_no & paid_after dari riwayat penuh.
     *
     * Nominal baris:
     *  - pembayaran biasa                      : nominal_receive / total_base_receive
     *  - bayar via overpay (overpay_id terisi) : nominal_spend / total_base_spend
     */
    public function getTicketPaymentReport(int $coaId, string $dateFrom, string $dateTo): array
    {
        $sql = "
        SELECT x.*
        FROM (
            SELECT
                t.id                 AS payment_id,
                t.id_parent_invoice  AS invoice_id,
                t.date_transaction,
                t.reference_detail,
                t.uuid_bank,
                t.overpay_id,
                IF(t.overpay_id IS NULL, t.nominal_receive, t.nominal_spend) AS amount,
                IF(t.overpay_id IS NULL,
                    COALESCE(t.total_base_receive, t.nominal_receive),
                    COALESCE(t.total_base_spend, t.nominal_spend)) AS amount_base,
                ROW_NUMBER() OVER (
                    PARTITION BY t.id_parent_invoice
                    ORDER BY t.date_transaction, t.id
                ) AS pay_no,
                SUM(IF(t.overpay_id IS NULL, t.nominal_receive, t.nominal_spend)) OVER (
                    PARTITION BY t.id_parent_invoice
                    ORDER BY t.date_transaction, t.id
                ) AS paid_after,
                i.invoice_number,
                i.invoice_total,
                i.contact_name,
                i.code_curr,
                i.status AS invoice_status,
                i.issue_date,
                d.coa_amount,
                d.other_lines
            FROM transaction_nominal_bank_accounts t
            JOIN invoices_all_from_xeros i ON i.id = t.id_parent_invoice
            JOIN (
                SELECT
                    parent_inv_id,
                    SUM(IF(coa_id = ?, total_amount_each_row, 0)) AS coa_amount,
                    SUM(IF(coa_id = ?, 0, 1))                     AS other_lines
                FROM item_detail_invoices
                GROUP BY parent_inv_id
                HAVING SUM(coa_id = ?) > 0
            ) d ON d.parent_inv_id = t.id_parent_invoice
            WHERE t.id_fee_overpay IS NULL
        ) x
        WHERE DATE(x.date_transaction) BETWEEN ? AND ?
        ORDER BY x.date_transaction, x.payment_id
    ";

        return DB::select($sql, [$coaId, $coaId, $coaId, $dateFrom, $dateTo]);
    }

}
