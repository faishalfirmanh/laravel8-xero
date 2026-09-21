<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Http\Repository\MasterData\CoaRepo;
use App\Http\Repository\Transaction\TransCoaRepo;
use App\Models\Transaction\TransactionAllCoa;
use DB;
use Illuminate\Http\Request;
use App\Http\Repository\LogHistoryRepository;
use App\Traits\ApiResponse;
use Validator;

use Illuminate\Support\Str;

class ProfitLossController extends Controller
{
    use ApiResponse;

    protected $repo, $repo_coa;

    public function __construct(TransCoaRepo $repo, CoaRepo $repo_coa)
    {
        $this->repo = $repo;
        $this->repo_coa = $repo_coa;
    }



    public function profitAndLossDetailData(Request $request, $account, $date_start, $date_end)
    {
        $track_paket = $request->query('track_paket');
        $track_divisi = $request->query('track_divisi');

        if ($track_paket !== null) {
            $track_paket = str_replace('?', '', $track_paket);
        }

        if ($track_divisi !== null) {
            $track_divisi = str_replace('?', '', $track_divisi);
        }

        $filterPaket = $track_paket ? explode(',', $track_paket) : [];
        $filterDivisi = $track_divisi ? explode(',', $track_divisi) : [];

        $coa = DB::table('coas')->where('id', $account)->first();

        if (!$coa) {
            return $this->error('Akun COA tidak ditemukan', 404);
        }

        $query = DB::table('transaction_all_coas as t')
            ->join('coas as c', 'c.id', '=', 't.uuid_coa')
            ->leftJoin('item_detail_invoices as di', 'di.uuid_detail_inv', '=', 't.uuid_detail')
            ->leftJoin('invoices_all_from_xeros as inv', 'inv.id', '=', 'di.parent_inv_id') // ⚠️ sesuaikan kolom FK
            ->leftJoin('d_bills as dbi', 'dbi.uuid_detail', '=', 't.uuid_detail')
            ->leftJoin('p_bills as pbi', 'pbi.id', '=', 'dbi.bills_parent_id')
            ->where('t.uuid_coa', $account)
            ->whereBetween('t.date_transaction', [$date_start, $date_end]);

        if (count($filterDivisi) > 0) {
            $query->where(function ($sub) use ($filterDivisi) {
                $sub->whereIn('di.divisi_travel_tracking_uuid', $filterDivisi)
                    ->orWhereIn('dbi.divisi_travel_tracking_uuid', $filterDivisi);
            });
        }

        if (count($filterPaket) > 0) {
            $query->where(function ($sub) use ($filterPaket) {
                $sub->whereIn('di.paket_tracking_uuid', $filterPaket)
                    ->orWhereIn('dbi.paket_tracking_uuid', $filterPaket);
            });
        }

        $transactions = $query
            ->selectRaw('
            t.id,
            t.date_transaction,
            t.nominal,
            t.is_speend,
            t.uuid_detail,
            inv.invoice_number,   -- ⚠️ sesuaikan nama kolom no. invoice
            pbi.reference   as bill_reference,  -- ⚠️ sesuaikan nama kolom no. bill
            di.desc  as invoice_desc,    -- ⚠️ sesuaikan kalau nama kolomnya beda
            dbi.desc as bill_desc        -- ⚠️ sesuaikan kalau nama kolomnya beda
        ')
            ->orderBy('t.date_transaction')
            ->get();

        $isRevenue = $coa->account_type === 'REVENUE';
        $total = 0;
        $items = $transactions->map(function ($t) use ($isRevenue, &$total) {
            $signed = $isRevenue
                ? ($t->is_speend == 0 ? $t->nominal : -$t->nominal)
                : ($t->is_speend == 1 ? $t->nominal : -$t->nominal);
            $total += $signed;

            return [
                'date' => $t->date_transaction,
                'nominal' => (float) $signed,
                'reference' => $t->invoice_number ?: $t->bill_reference,
                'description' => $t->invoice_desc ?: $t->bill_desc,
                'source' => $t->invoice_number ? 'invoice' : ($t->bill_reference ? 'bill' : null),
            ];
        })->values();

        return $this->autoResponse([
            'coa' => ['id' => $coa->id, 'name' => $coa->name, 'account_type' => $coa->account_type],
            'period' => ['date_start' => $date_start, 'date_end' => $date_end],
            'transactions' => $items,
            'total' => (float) $total,
        ]);
    }

    public function getHome(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'date_start' => 'required|date',
            'date_end' => 'required|date',
            'tracking_divisi' => 'nullable|array',
            'tracking_divisi.*' => 'nullable|string',
            'tracking_paket_name' => 'nullable|array',
            'tracking_paket_name.*' => 'nullable|string',
            'currency' => 'nullable|string|size:3',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 404);
        }

        $dateStart = $request->date_start;
        $dateEnd = $request->date_end;

        $filterDivisi = array_filter((array) ($request->tracking_divisi ?? []));
        $filterPaket = array_filter((array) ($request->tracking_paket_name ?? []));

        $hasFilterDivisi = count($filterDivisi) > 0;
        $hasFilterPaket = count($filterPaket) > 0;

        // ================================================================
        // ✅ FIXED: BASE QUERY
        //    - t.id disertakan sebagai uniqueness key agar UNION tidak
        //      salah menghapus baris transaksi berbeda yang nilainya sama
        //    - unionAll → union supaya duplikat hasil join benar-benar hilang
        // ================================================================
        $baseQuery = function () use ($dateStart, $dateEnd, $filterDivisi, $filterPaket, $hasFilterDivisi, $hasFilterPaket) {
            // Dari invoice line items
            $invoiceQuery = DB::table('transaction_all_coas as t')
                ->join('coas as c', 'c.id', '=', 't.uuid_coa')
                ->leftJoin('item_detail_invoices as di', 'di.uuid_detail_inv', '=', 't.uuid_detail')
                ->whereBetween('t.date_transaction', [$dateStart, $dateEnd])
                ->whereNotNull('di.id')
                ->select(
                    't.id as trx_row_id',   // ← uniqueness key
                    't.uuid_detail',
                    't.date_transaction',
                    't.is_speend',
                    't.base_nominal',
                    't.nominal_currency',
                    't.code_curr',
                    'c.id',                 // coa id
                    'c.name',
                    'c.account_type'
                );

            if ($hasFilterDivisi) {
                $invoiceQuery->whereIn('di.divisi_travel_tracking_uuid', $filterDivisi);
            }

            if ($hasFilterPaket) {
                $invoiceQuery->whereIn('di.paket_tracking_uuid', $filterPaket);
            }

            // Dari bill line items
            $billQuery = DB::table('transaction_all_coas as t')
                ->join('coas as c', 'c.id', '=', 't.uuid_coa')
                ->leftJoin('d_bills as dbi', 'dbi.uuid_detail', '=', 't.uuid_detail')
                ->whereBetween('t.date_transaction', [$dateStart, $dateEnd])
                ->whereNotNull('dbi.id')
                ->select(
                    't.id as trx_row_id',   // ← uniqueness key
                    't.uuid_detail',
                    't.date_transaction',
                    't.is_speend',
                    't.base_nominal',
                    't.nominal_currency',
                    't.code_curr',
                    'c.id',
                    'c.name',
                    'c.account_type'
                );

            if ($hasFilterDivisi) {
                $billQuery->whereIn('dbi.divisi_travel_tracking_uuid', $filterDivisi);
            }

            if ($hasFilterPaket) {
                $billQuery->whereIn('dbi.paket_tracking_uuid', $filterPaket);
            }

            // ✅ UNION — baris kembar (hasil join) hilang, transaksi berbeda tetap ada
            return $invoiceQuery->union($billQuery);
        };

        // ================================================================
        // Helper ambil rows + total per kelompok account_type.
        // ================================================================
        $getSection = function (array $accountTypes, bool $isExpenseLike) use ($baseQuery) {
            $normalSpend = $isExpenseLike ? 1 : 0;

            $rows = DB::query()
                ->fromSub(
                    $baseQuery(),
                    'unified'
                )
                ->whereIn('unified.account_type', $accountTypes)
                ->selectRaw("
                unified.id,
                unified.name,
                SUM(CASE WHEN unified.is_speend = {$normalSpend} THEN unified.base_nominal ELSE -unified.base_nominal END) as total
            ")
                ->groupBy('unified.id', 'unified.name')
                ->get();

            return [
                'items' => $rows->map(fn($r) => [
                    'coa_id' => $r->id,
                    'name' => $r->name,
                    'total' => (float) $r->total,
                ])->values(),
                'total' => (float) $rows->sum('total'),
            ];
        };
        // ================================================================
        // Mapping ke section P&L Xero
        // ================================================================
        $tradingIncome = $getSection(['REVENUE'], false);
        $costOfSales = $getSection(['DIRECTCOSTS', 'EXPENSE'], true);
        $otherIncome = $getSection(['OTHERINCOME'], false);
        $operatingExpenses = $getSection(['EXPENSE'], true);

        $grossProfit = $tradingIncome['total'] - $costOfSales['total'];
        $netProfit = $grossProfit + $otherIncome['total'] - $operatingExpenses['total'];

        $data = [
            'period' => [
                'date_start' => $dateStart,
                'date_end' => $dateEnd,
            ],
            'active_filters' => [
                'tracking_divisi' => $hasFilterDivisi ? array_values($filterDivisi) : null,
                'tracking_paket_name' => $hasFilterPaket ? array_values($filterPaket) : null,
            ],
            'trading_income' => $tradingIncome,
            'cost_of_sales' => $costOfSales,
            'gross_profit' => $grossProfit,
            'other_income' => $otherIncome,
            'operating_expenses' => $operatingExpenses,
            'net_profit' => $netProfit,
        ];

        return $this->autoResponse($data);
    }

    public function getData(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'page' => 'required|integer',
            'limit' => 'required|integer',
            'kolom_name' => 'required|string',
            'keyword' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 404);
        }

        $where = [];

        if ($request->keyword != null) {
            $data = $this->repo->searchData(
                $where,
                $request->limit,
                $request->page,
                $request->kolom_name,
                strtoupper($request->keyword)
            );
        } else {
            $data = $this->repo->getAllDataWithDefault(
                $where,
                $request->limit,
                $request->page,
                'id',
                'desc'
            );
        }

        return $this->autoResponse($data);
    }
}
