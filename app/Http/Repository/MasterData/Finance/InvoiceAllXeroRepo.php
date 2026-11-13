<?php

namespace App\Http\Repository\MasterData\Finance;

use App\Http\Repository\BaseRepository;
use App\Http\Repository\Support\PaketLineFilterSql;
use App\Models\InvoicesAllFromXero;
use DB;

class InvoiceAllXeroRepo extends BaseRepository
{
    use PaketLineFilterSql;
    public function __construct(InvoicesAllFromXero $model)
    {
        parent::__construct($model);
    }

    public function getDueUnpaidByPaket(int $coaId, array $itemIds, array $trackingUuids, string $dateFrom, string $dateTo, bool $includeOverdue = false): array
    {
        list($f, $b) = $this->paketLineFilter('l', $itemIds, $trackingUuids);
        list($f3, $b3) = $this->paketLineFilter('l3', $itemIds, $trackingUuids);

        $dueSql = $includeOverdue ? 'DATE(i.due_date) <= ?' : 'DATE(i.due_date) BETWEEN ? AND ?';
        $dueBindings = $includeOverdue ? [$dateTo] : [$dateFrom, $dateTo];

        $sql = "
        SELECT x.*
        FROM (
            SELECT
                i.id AS invoice_id,
                i.invoice_number,
                i.contact_name,
                i.issue_date,
                i.due_date,
                i.invoice_total,
                i.code_curr,
                i.status AS invoice_status,
                d.item_amount,
                d.other_lines,
                j.phone_number,
                COALESCE(p.paid_total, 0) AS paid_total,
                GREATEST(i.invoice_total - COALESCE(p.paid_total, 0), 0) AS sisa,
                GREATEST(i.invoice_total - COALESCE(p.paid_total, 0), 0) * COALESCE(NULLIF(i.nominal_currency, 0), 1) AS sisa_base,
                GREATEST(DATEDIFF(CURDATE(), DATE(i.due_date)), 0) AS overdue_days
            FROM invoices_all_from_xeros i
            LEFT JOIN data_jamaah_xeros j ON j.id = i.contact_id
            JOIN (
                SELECT
                    l.parent_inv_id,
                    SUM(IF(l.coa_id = ? AND {$f}, l.total_amount_each_row, 0)) AS item_amount,
                    SUM(IF(l.coa_id = ? AND {$f}, 0, 1))                       AS other_lines
                FROM item_detail_invoices l
                GROUP BY l.parent_inv_id
                HAVING SUM(l.coa_id = ? AND {$f}) > 0
            ) d ON d.parent_inv_id = i.id
            LEFT JOIN (
                SELECT
                    id_parent_invoice,
                    SUM(IF(overpay_id IS NULL, nominal_receive, nominal_spend)) AS paid_total
                FROM transaction_nominal_bank_accounts
                WHERE id_parent_invoice IS NOT NULL
                  AND id_fee_overpay IS NULL
                  AND id_parent_invoice IN (
                      SELECT l3.parent_inv_id FROM item_detail_invoices l3
                      WHERE l3.coa_id = ? AND {$f3}
                  )
                GROUP BY id_parent_invoice
            ) p ON p.id_parent_invoice = i.id
            WHERE UPPER(i.status) = 'AUTHORISED'
              AND {$dueSql}
        ) x
        WHERE x.sisa > 0.01
        ORDER BY x.due_date, x.invoice_number
    ";

        $bindings = array_merge(
            [$coaId],
            $b,           // item_amount
            [$coaId],
            $b,           // other_lines
            [$coaId],
            $b,           // HAVING
            [$coaId],
            $b3,          // subquery p
            $dueBindings
        );

        return DB::select($sql, $bindings);
    }

    public function sumDataWhereIn($list_id = array(), string $kolom)
    {
        $data = $this->model->whereIn('invoice_uuid', $list_id)->sum($kolom);
        return $data;
    }

    public function getWhereDataIn($list_id = array())
    {
        $data = $this->model->whereIn('invoice_uuid', $list_id)->get();
        return $data;
    }

    public function getDueUnpaidByCoa(int $coaId, string $dateFrom, string $dateTo, bool $includeOverdue = false): array
    {
        $dueSql = $includeOverdue ? 'DATE(i.due_date) <= ?' : 'DATE(i.due_date) BETWEEN ? AND ?';
        $dueBindings = $includeOverdue ? [$dateTo] : [$dateFrom, $dateTo];

        $sql = "
        SELECT x.*
        FROM (
            SELECT
                i.id AS invoice_id,
                i.invoice_number,
                i.contact_name,
                i.issue_date,
                i.due_date,
                i.invoice_total,
                i.code_curr,
                i.status AS invoice_status,
                d.coa_amount,
                d.other_lines,
                j.phone_number,
                COALESCE(p.paid_total, 0) AS paid_total,
                GREATEST(i.invoice_total - COALESCE(p.paid_total, 0), 0) AS sisa,
                GREATEST(i.invoice_total - COALESCE(p.paid_total, 0), 0) * COALESCE(NULLIF(i.nominal_currency, 0), 1) AS sisa_base,
                GREATEST(DATEDIFF(CURDATE(), DATE(i.due_date)), 0) AS overdue_days
            FROM invoices_all_from_xeros i
            LEFT JOIN data_jamaah_xeros j ON j.id = i.contact_id
            JOIN (
                SELECT
                    parent_inv_id,
                    SUM(IF(coa_id = ?, total_amount_each_row, 0)) AS coa_amount,
                    SUM(IF(coa_id = ?, 0, 1))                     AS other_lines
                FROM item_detail_invoices
                GROUP BY parent_inv_id
                HAVING SUM(coa_id = ?) > 0
            ) d ON d.parent_inv_id = i.id
            LEFT JOIN (
                SELECT
                    id_parent_invoice,
                    SUM(IF(overpay_id IS NULL, nominal_receive, nominal_spend)) AS paid_total
                FROM transaction_nominal_bank_accounts
                WHERE id_parent_invoice IS NOT NULL
                  AND id_fee_overpay IS NULL
                  AND id_parent_invoice IN (SELECT parent_inv_id FROM item_detail_invoices WHERE coa_id = ?)
                GROUP BY id_parent_invoice
            ) p ON p.id_parent_invoice = i.id
            WHERE UPPER(i.status) = 'AUTHORISED'
              AND {$dueSql}
        ) x
        WHERE x.sisa > 0.01
        ORDER BY x.due_date, x.invoice_number
    ";

        return DB::select($sql, array_merge([$coaId, $coaId, $coaId, $coaId], $dueBindings));
    }
}
