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

        // Filter tracking — bersifat opsional, kosong = tidak difilter
        $filterDivisi = array_filter((array) ($request->tracking_divisi ?? []));
        $filterPaket = array_filter((array) ($request->tracking_paket_name ?? []));

        $hasFilterDivisi = count($filterDivisi) > 0;
        $hasFilterPaket = count($filterPaket) > 0;

        // ================================================================
        // BASE QUERY BUILDER — dipakai ulang untuk income & cost of sales
        // ================================================================
        // LEFT JOIN ke item_detail_invoices karena:
        //   - Transaksi dari invoice (REVENUE) → punya uuid_detail_inv
        //   - Transaksi dari bill/spend (EXPENSE) → bisa tidak punya relasi di sana
        // Filter tracking hanya berlaku kalau ada isinya (whereIn kondisional).
        // ================================================================

        $baseQuery = function () use ($dateStart, $dateEnd, $filterDivisi, $filterPaket, $hasFilterDivisi, $hasFilterPaket) {
            $q = DB::table('transaction_all_coas as t')
                ->join('coas as c', 'c.id', '=', 't.uuid_coa')
                ->leftJoin('item_detail_invoices as di', 'di.uuid_detail_inv', '=', 't.uuid_detail')
                ->leftJoin('d_bills as dbi', 'dbi.uuid_detail', '=', 't.uuid_detail')
                ->whereBetween('t.date_transaction', [$dateStart, $dateEnd]);

            // Filter tracking_divisi — hanya aktif kalau ada nilai
            if ($hasFilterDivisi) {
                $q->where(function ($sub) use ($filterDivisi) {
                    $sub->whereIn('di.divisi_travel_tracking_uuid', $filterDivisi)
                        ->orWhereIn('dbi.divisi_travel_tracking_uuid', $filterDivisi);
                });
            }

            // Filter tracking_paket_name — hanya aktif kalau ada nilai
            if ($hasFilterPaket) {
                $q->where(function ($sub) use ($filterPaket) {
                    $sub->whereIn('di.paket_tracking_uuid', $filterPaket)
                        ->orWhereIn('dbi.paket_tracking_uuid', $filterPaket);
                });
            }

            return $q;
        };

        // ── Trading Income (REVENUE) ─────────────────────────────────────
        // is_speend = 0 → income; is_speend = 1 → koreksi/retur (nilai negatif)
        $tradingIncomeRows = $baseQuery()
            ->where('c.account_type', 'REVENUE')
            ->selectRaw('
            c.id,
            c.name,
            SUM(CASE WHEN t.is_speend = 0 THEN t.nominal ELSE -t.nominal END) as total
        ')
            ->groupBy('c.id', 'c.name')
            ->get();

        $totalTradingIncome = $tradingIncomeRows->sum('total');

        // ── Cost of Sales (EXPENSE) ──────────────────────────────────────
        // is_speend = 1 → pengeluaran; is_speend = 0 → koreksi (nilai negatif)
        $costOfSalesRows = $baseQuery()
            ->where('c.account_type', 'EXPENSE')
            ->selectRaw('
            c.id,
            c.name,
            SUM(CASE WHEN t.is_speend = 1 THEN t.nominal ELSE -t.nominal END) as total
        ')
            ->groupBy('c.id', 'c.name')
            ->get();

        $totalCostOfSales = $costOfSalesRows->sum('total');

        // ── Gross & Net Profit ───────────────────────────────────────────
        $grossProfit = (float) $totalTradingIncome - (float) $totalCostOfSales;
        $netProfit = $grossProfit; // tambah operating expense di sini kalau ada

        // ── Build response ───────────────────────────────────────────────
        $data = [
            'period' => [
                'date_start' => $dateStart,
                'date_end' => $dateEnd,
            ],

            // Filter yang aktif — berguna untuk debug / label di frontend
            'active_filters' => [
                'tracking_divisi' => $hasFilterDivisi ? array_values($filterDivisi) : null,
                'tracking_paket_name' => $hasFilterPaket ? array_values($filterPaket) : null,
            ],

            'trading_income' => [
                'items' => $tradingIncomeRows->map(function ($r) {
                    return [
                        'name' => $r->name,
                        'total' => (float) $r->total,
                    ];
                })->values(),
                'total' => (float) $totalTradingIncome,
            ],

            'cost_of_sales' => [
                'items' => $costOfSalesRows->map(function ($r) {
                    return [
                        'name' => $r->name,
                        'total' => (float) $r->total,
                    ];
                })->values(),
                'total' => (float) $totalCostOfSales,
            ],

            'gross_profit' => $grossProfit,
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
