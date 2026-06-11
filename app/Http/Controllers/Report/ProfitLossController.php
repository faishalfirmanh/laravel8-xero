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
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 404);
        }

        $dateStart = $request->date_start;
        $dateEnd = $request->date_end;

        // ── Trading Income ────────────────────────────────────────────────
        $tradingIncomeRows = DB::table('transaction_all_coas as t')
            ->join('coas as c', 'c.id', '=', 't.uuid_coa')
            ->whereBetween('t.date_transaction', [$dateStart, $dateEnd])
            ->where('c.account_type', 'REVENUE')
            ->selectRaw('c.name, SUM(CASE WHEN t.is_speend = 0 THEN t.nominal ELSE -t.nominal END) as total')
            ->groupBy('c.id', 'c.name')
            ->get();

        $totalTradingIncome = $tradingIncomeRows->sum('total');

        // ── Cost of Sales ─────────────────────────────────────────────────
        $costOfSalesRows = DB::table('transaction_all_coas as t')
            ->join('coas as c', 'c.id', '=', 't.uuid_coa')
            ->whereBetween('t.date_transaction', [$dateStart, $dateEnd])
            ->where('c.account_type', 'EXPENSE')
            ->selectRaw('c.name, SUM(CASE WHEN t.is_speend = 1 THEN t.nominal ELSE -t.nominal END) as total')
            ->groupBy('c.id', 'c.name')
            ->get();

        $totalCostOfSales = $costOfSalesRows->sum('total');

        // ── Gross Profit ──────────────────────────────────────────────────
        $grossProfit = $totalTradingIncome - $totalCostOfSales;

        // ── Net Profit ────────────────────────────────────────────────────
        // Tambahkan operating expenses di sini jika ada tipe lain (misal OPERATING_EXPENSE)
        $netProfit = $grossProfit;

        $data = [
            'period' => [
                'date_start' => $dateStart,
                'date_end' => $dateEnd,
            ],
            'trading_income' => [
                'items' => $tradingIncomeRows->map(fn($r) => [
                    'name' => $r->name,
                    'total' => (float) $r->total,
                ])->values(),
                'total' => (float) $totalTradingIncome,
            ],
            'cost_of_sales' => [
                'items' => $costOfSalesRows->map(fn($r) => [
                    'name' => $r->name,
                    'total' => (float) $r->total,
                ])->values(),
                'total' => (float) $totalCostOfSales,
            ],
            'gross_profit' => (float) $grossProfit,
            'net_profit' => (float) $netProfit,
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
