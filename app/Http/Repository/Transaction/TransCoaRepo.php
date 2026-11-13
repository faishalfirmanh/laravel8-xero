<?php
namespace App\Http\Repository\Transaction;

use App\Http\Repository\BaseRepository;

use App\Models\Transaction\TransactionAllCoa;
use Illuminate\Support\Facades\DB;
class TransCoaRepo extends BaseRepository
{
    private const PL_TYPES = ['REVENUE', 'DIRECTCOSTS', 'OTHERINCOME', 'EXPENSE'];
    private const NO_PAKET = 'TANPA PAKET';

    public function __construct(TransactionAllCoa $model)
    {
        $this->model = $model;
    }

    public function wherenDataIn($column, $value)
    {
        return $this->model->whereIn($column, $value);
    }

    public function searchDataMultiColumn($where = [], $per_page = 10, array $search_columns = [], $keyword = "", $modelWith = [], array $dateRange = [])
    {
        $query = $this->model->with($modelWith)->where($where);

        // ── DATE RANGE FILTER ──
        if (!empty($dateRange['column'])) {
            if (!empty($dateRange['start'])) {
                $query->whereDate($dateRange['column'], '>=', $dateRange['start']);
            }
            if (!empty($dateRange['end'])) {
                $query->whereDate($dateRange['column'], '<=', $dateRange['end']);
            }
        }

        if (!empty($keyword) && !empty($search_columns)) {
            $query->where(function ($q) use ($search_columns, $keyword) {
                $isDateKeyword = (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $keyword);

                foreach ($search_columns as $key => $value) {
                    if (is_array($value)) {
                        $relationName = $key;
                        $relationColumns = $value;

                        $q->orWhereHas($relationName, function ($relQuery) use ($relationColumns, $keyword) {
                            $relQuery->where(function ($subQ) use ($relationColumns, $keyword) {
                                foreach ($relationColumns as $relCol) {
                                    $subQ->orWhere($relCol, 'LIKE', '%' . $keyword . '%');
                                }
                            });
                        });
                    } else {
                        $colName = is_string($key) ? $key : $value;
                        $colType = is_string($key) ? strtolower($value) : 'string';

                        if ($colType === 'date' || $colType === 'datetime') {
                            if ($isDateKeyword) {
                                $q->orWhereDate($colName, $keyword);
                            }
                        } else {
                            $q->orWhere($colName, 'LIKE', '%' . $keyword . '%');
                        }
                    }
                }
            });
        }

        $per_page = $per_page > 0 ? $per_page : $query->count();

        return $query->paginate($per_page);
    }

    public function getAllDataWithDefault($where = array(), $per_page = 10, $offset = 1, $sort_column, $sort_order = "ASC", $modelWith = [], array $dateRange = [])
    {
        $query = $this->model->with($modelWith)->where($where);

        // ── DATE RANGE FILTER ──
        if (!empty($dateRange['column'])) {
            if (!empty($dateRange['start'])) {
                $query->whereDate($dateRange['column'], '>=', $dateRange['start']);
            }
            if (!empty($dateRange['end'])) {
                $query->whereDate($dateRange['column'], '<=', $dateRange['end']);
            }
        }

        $data = $query->orderBy($sort_column, $sort_order)->paginate($per_page);

        return $data;
    }

    /** baru untuk menu p. yasin */
    public function profitLossByPaket(string $dateStart, string $dateEndExclusive, array $paketIds, array $divisi, array $trackPaket)
    {
        $noPaket = self::NO_PAKET;

        return $this->baseQuery($dateStart, $dateEndExclusive, $paketIds, $divisi, $trackPaket)
            ->selectRaw("
                COALESCE(m.paket_name, '{$noPaket}') as paket_name,
                c.id           as coa_id,
                c.name         as coa_name,
                c.account_type as account_type,
                SUM(
                    CASE
                        WHEN c.account_type IN ('REVENUE', 'OTHERINCOME')
                            THEN CASE WHEN t.is_speend = 0 THEN t.base_nominal ELSE -t.base_nominal END
                        ELSE CASE WHEN t.is_speend = 1 THEN t.base_nominal ELSE -t.base_nominal END
                    END
                ) as total
            ")
            ->groupByRaw("COALESCE(m.paket_name, '{$noPaket}'), c.id, c.name, c.account_type")
            ->orderBy('c.name')
            ->get();
    }

    /**
     * Jumlah pax per paket: SUM(qty) baris invoice pada akun REVENUE,
     * dihitung sekali per baris detail (uuid_detail).
     */
    public function paxByPaket(string $dateStart, string $dateEndExclusive, array $paketIds, array $divisi, array $trackPaket)
    {
        $noPaket = self::NO_PAKET;

        $inner = $this->baseQuery($dateStart, $dateEndExclusive, $paketIds, $divisi, $trackPaket)
            ->where('c.account_type', 'REVENUE')
            ->where('t.is_speend', 0)
            ->where('m.qty', '>', 0)
            ->selectRaw("COALESCE(m.paket_name, '{$noPaket}') as paket_name, t.uuid_detail, MAX(m.qty) as qty")
            ->groupByRaw("COALESCE(m.paket_name, '{$noPaket}'), t.uuid_detail");

        return DB::query()
            ->fromSub($inner, 'x')
            ->selectRaw('x.paket_name, SUM(x.qty) as pax')
            ->groupBy('x.paket_name')
            ->get();
    }

    /**
     * Info item paket untuk active_filters.
     * Pindahkan ke repo item paket kalau sudah ada.
     */
    public function getPaketByIds(array $ids)
    {
        if (empty($ids)) {
            return collect();
        }

        return DB::table('items_paket_all_from_xeros')
            ->whereIn('id', $ids)
            ->get(['id', 'nama_paket']);
    }

    // ─────────────────────────────────────────────────────────────

    /**
     * Transaksi di rentang tanggal, hanya akun P&L, di-join dengan peta paket per uuid_detail.
     * Ada filter  -> inner join (sama seperti V2: fee overpay & transaksi tanpa detail tidak ikut).
     * Tanpa filter -> left join, transaksi yang paketnya tidak ketemu masuk 'TANPA PAKET'.
     */
    private function baseQuery(string $dateStart, string $dateEndExclusive, array $paketIds, array $divisi, array $trackPaket)
    {
        $query = DB::table('transaction_all_coas as t')
            ->join('coas as c', 'c.id', '=', 't.uuid_coa')
            ->where('t.date_transaction', '>=', $dateStart)
            ->where('t.date_transaction', '<', $dateEndExclusive)
            ->whereIn('c.account_type', self::PL_TYPES);

        $map = $this->detailPaketMap($paketIds, $divisi, $trackPaket);
        $hasFilter = !empty($paketIds) || !empty($divisi) || !empty($trackPaket);

        return $hasFilter
            ? $query->joinSub($map, 'm', 'm.uuid_detail', '=', 't.uuid_detail')
            : $query->leftJoinSub($map, 'm', 'm.uuid_detail', '=', 't.uuid_detail');
    }

    /**
     * Peta: uuid_detail -> paket_name (+ qty untuk invoice), dari 3 sumber detail
     * (invoice, bill, bank). Sekaligus menerapkan filter:
     * kelompok ITEM atau kelompok TRACKING (sama dengan buildDetailFilterUnion di V2).
     *
     * Nama paket: tracking "Nama Paket" dulu (data lama), kalau kosong baru item paket (transaksi baru).
     * ASUMSI kolom tracking_categories: name_parent_category, item_uuid_category (uuid), lines_category (nama).
     */
    private function detailPaketMap(array $paketIds, array $divisi, array $trackPaket)
    {
        $hasItem = !empty($paketIds);
        $hasTracking = !empty($divisi) || !empty($trackPaket);
        $hasFilter = $hasItem || $hasTracking;

        // join nama tracking "Nama Paket"
        $joinTracking = function ($join) {
            $join->on('tp.item_uuid_category', '=', 'd.paket_tracking_uuid')
                ->whereRaw("LOWER(tp.name_parent_category) = 'nama paket'");
        };

        // kondisi tracking: divisi AND nama paket (sama seperti V2)
        $trackCond = function ($q) use ($divisi, $trackPaket) {
            if (!empty($divisi)) {
                $q->whereIn('d.divisi_travel_tracking_uuid', $divisi);
            }
            if (!empty($trackPaket)) {
                $q->whereIn('d.paket_tracking_uuid', $trackPaket);
            }
        };

        // ── Invoice ──────────────────────────────────────────────
        $invoice = DB::table('item_detail_invoices as d')
            ->leftJoin('items_paket_all_from_xeros as ip', 'ip.id', '=', 'd.item_id')
            ->leftJoin('tracking_categories as tp', $joinTracking)
            ->selectRaw('d.uuid_detail_inv as uuid_detail, COALESCE(tp.lines_category, ip.nama_paket) as paket_name, d.qty as qty');

        // ── Bill ─────────────────────────────────────────────────
        $bill = DB::table('d_bills as d')
            ->leftJoin('items_paket_all_from_xeros as ip1', 'ip1.id', '=', 'd.item_id_prod_serv')
            ->leftJoin('items_paket_all_from_xeros as ip2', function ($j) {
                $j->on('ip2.code', '=', 'd.item_code')->where('ip2.code', '<>', '');
            })
            ->leftJoin('tracking_categories as tp', $joinTracking)
            ->selectRaw('d.uuid_detail as uuid_detail, COALESCE(tp.lines_category, ip1.nama_paket, ip2.nama_paket) as paket_name, 0 as qty');

        // ── Bank (item_code berisi id paket) ─────────────────────
        $bank = DB::table('transaction_bank_trans_d_s as d')
            ->leftJoin('items_paket_all_from_xeros as ip', 'ip.id', '=', 'd.item_code')
            ->leftJoin('tracking_categories as tp', $joinTracking)
            ->selectRaw('d.uuid_detail_trans_bank as uuid_detail, COALESCE(tp.lines_category, ip.nama_paket) as paket_name, 0 as qty');

        if ($hasFilter) {
            $invoice->where(function ($w) use ($hasItem, $hasTracking, $paketIds, $trackCond) {
                if ($hasItem) {
                    $w->orWhereIn('d.item_id', $paketIds);
                }
                if ($hasTracking) {
                    $w->orWhere(function ($x) use ($trackCond) {
                        $trackCond($x);
                    });
                }
            });

            $bill->where(function ($w) use ($hasItem, $hasTracking, $paketIds, $trackCond) {
                if ($hasItem) {
                    $w->orWhereIn('d.item_id_prod_serv', $paketIds);
                    // d_bills.item_code = code paket
                    $w->orWhereIn('d.item_code', function ($s) use ($paketIds) {
                        $s->from('items_paket_all_from_xeros')
                            ->select('code')
                            ->whereIn('id', $paketIds)
                            ->whereNotNull('code')
                            ->where('code', '<>', '');
                    });
                }
                if ($hasTracking) {
                    $w->orWhere(function ($x) use ($trackCond) {
                        $trackCond($x);
                    });
                }
            });

            $bank->where(function ($w) use ($hasItem, $hasTracking, $paketIds, $trackCond) {
                if ($hasItem) {
                    $w->orWhereIn('d.item_code', $paketIds);
                }
                if ($hasTracking) {
                    $w->orWhere(function ($x) use ($trackCond) {
                        $trackCond($x);
                    });
                }
            });
        }

        return $invoice->union($bill)->union($bank);
    }
}
