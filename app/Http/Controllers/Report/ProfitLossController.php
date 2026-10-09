<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Http\Repository\MasterData\CoaRepo;
use App\Http\Repository\MasterData\TrackingRepo;
use App\Http\Repository\Transaction\TransCoaRepo;
use App\Models\Transaction\TransactionAllCoa;
use DB;
use Illuminate\Http\Request;
use App\Http\Repository\LogHistoryRepository;
use App\Traits\ApiResponse;
use Illuminate\Support\Carbon;
use Validator;

use Illuminate\Support\Str;

class ProfitLossController extends Controller
{
    use ApiResponse;

    protected $repo, $repo_coa, $tracing;

    public function __construct(TransCoaRepo $repo, CoaRepo $repo_coa, TrackingRepo $tracing)
    {
        $this->repo = $repo;
        $this->repo_coa = $repo_coa;
        $this->tracing = $tracing;
    }


    private function parseTrackingFilter($raw): array
    {
        if ($raw === null) {
            return [];
        }

        $parts = array_map('trim', explode(',', str_replace('?', '', $raw)));

        return array_values(array_filter($parts, function ($v) {
            return $v !== '';
        }));
    }

    public function profitAndLossDetailData(Request $request, $account, $date_start, $date_end)
    {
        // ── 1. Validasi tanggal ──────────────────────────────────────────
        try {
            $start = Carbon::parse($date_start)->startOfDay();
            $end = Carbon::parse($date_end)->endOfDay();
        } catch (\Exception $e) {
            return $this->error('Format tanggal tidak valid', 422);
        }

        if ($start->gt($end)) {
            return $this->error('date_start tidak boleh lebih besar dari date_end', 422);
        }

        $filterPaket = $this->parseTrackingFilter($request->query('track_paket'));
        $filterDivisi = $this->parseTrackingFilter($request->query('track_divisi'));

        $coa = DB::table('coas')->where('id', $account)->first();

        if (!$coa) {
            return $this->error('Akun COA tidak ditemukan', 404);
        }

        // Satu baris per kontak supaya join jamaah tidak menggandakan transaksi
        $jamaahSub = DB::table('data_jamaah_xeros')
            ->select('uuid_contact', DB::raw('MIN(full_name) as full_name'))
            ->whereNotNull('uuid_contact')
            ->where('uuid_contact', '<>', '')
            ->groupBy('uuid_contact');

        // ── 2. Query: HANYA invoice receivable & receive money ──────────
        $query = DB::table('transaction_all_coas as t')
            ->leftJoin('item_detail_invoices as di', 'di.uuid_detail_inv', '=', 't.uuid_detail')
            ->leftJoin('invoices_all_from_xeros as inv', 'inv.id', '=', 'di.parent_inv_id')
            ->leftJoin('transaction_bank_trans_d_s as dtbi', 'dtbi.uuid_detail_trans_bank', '=', 't.uuid_detail')
            ->leftJoin('transaction_bank_trans_p_s as ptbi', 'ptbi.id', '=', 'dtbi.trans_bank_parent_id')
            ->leftJoinSub($jamaahSub, 'jamaah_bank', 'jamaah_bank.uuid_contact', '=', 'ptbi.uuid_to')
            // FIX: sebelumnya inv.contact_id (ID lokal integer), padahal join ke uuid_contact
            ->leftJoinSub($jamaahSub, 'jamaah_invoice', 'jamaah_invoice.uuid_contact', '=', 'inv.uuid_contact')
            ->where('t.uuid_coa', $account)
            ->where('t.date_transaction', '>=', $start->toDateTimeString())
            ->where('t.date_transaction', '<=', $end->toDateTimeString())
            ->where(function ($src) {
                // Sumber 1: invoice receivable yang sudah authorised/paid (sesuai P&L Xero)
                $src->where(function ($inv) {
                    $inv->whereNotNull('di.id')
                        ->whereIn('inv.status', ['AUTHORISED', 'PAID']);
                })
                    // Sumber 2: receive money (uang masuk)
                    ->orWhere(function ($bank) {
                    $bank->whereNotNull('dtbi.id')
                        ->where('t.is_speend', 0);
                });
            });

        if (count($filterDivisi) > 0) {
            $query->where(function ($sub) use ($filterDivisi) {
                $sub->whereIn('di.divisi_travel_tracking_uuid', $filterDivisi)
                    ->orWhereIn('dtbi.divisi_travel_tracking_uuid', $filterDivisi);
            });
        }

        if (count($filterPaket) > 0) {
            $query->where(function ($sub) use ($filterPaket) {
                $sub->whereIn('di.paket_tracking_uuid', $filterPaket)
                    ->orWhereIn('dtbi.paket_tracking_uuid', $filterPaket);
            });
        }

        $transactions = $query
            ->selectRaw("
            t.id,
            t.date_transaction,
            t.base_nominal as nominal,
            t.is_speend,
            t.uuid_detail,
            inv.invoice_number,
            inv.reference as invoice_reference,
            ptbi.reference as bank_reference,
            di.desc as invoice_desc,
            dtbi.desc as bank_desc,
            CASE
                WHEN di.id IS NOT NULL THEN jamaah_invoice.full_name
                WHEN dtbi.id IS NOT NULL THEN jamaah_bank.full_name
                ELSE NULL
            END as jamaah_name,
            CASE
                WHEN di.id IS NOT NULL THEN 'invoice'
                WHEN dtbi.id IS NOT NULL THEN 'bank'
                ELSE NULL
            END as source
        ")
            ->orderBy('t.date_transaction')
            ->orderBy('t.id')
            ->get()
            ->unique('id')   // pengaman: satu baris per transaksi
            ->values();

        // ── 3. Tanda +/- ─────────────────────────────────────────────────
        // FIX: sebelumnya hanya 'REVENUE'; tipe SALES & OTHERINCOME ikut dianggap expense
        $isRevenue = in_array(strtoupper((string) $coa->account_type), ['REVENUE', 'SALES', 'OTHERINCOME'], true);
        $total = 0.0;

        $items = $transactions->map(function ($t) use ($isRevenue, &$total) {
            $nominal = (float) $t->nominal;

            $signed = $isRevenue
                ? ((int) $t->is_speend === 0 ? $nominal : -$nominal)
                : ((int) $t->is_speend === 1 ? $nominal : -$nominal);

            $total += $signed;

            $desc = $t->invoice_desc ?? $t->bank_desc;

            $parts = array_filter([$t->jamaah_name, $desc], function ($v) {
                return $v !== null && trim($v) !== '';
            });

            return [
                'date' => $t->date_transaction,
                'nominal' => $signed,
                'reference' => $t->source === 'invoice'
                    ? ($t->invoice_number ?? $t->invoice_reference)
                    : $t->bank_reference,
                'description' => count($parts) ? implode(' - ', $parts) : null,
                'source' => $t->source,
            ];
        })->values();

        return $this->autoResponse([
            'coa' => ['id' => $coa->id, 'name' => $coa->name, 'account_type' => $coa->account_type],
            'period' => ['date_start' => $date_start, 'date_end' => $date_end],
            'transactions' => $items,
            'total' => $total,
        ]);
    }

    public function profitAndLossDetailDataOLd(Request $request, $account, $date_start, $date_end)
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
            ->leftJoin('item_detail_invoices as di', 'di.uuid_detail_inv', '=', 't.uuid_detail')
            ->leftJoin('invoices_all_from_xeros as inv', 'inv.id', '=', 'di.parent_inv_id')
            ->leftJoin('d_bills as dbi', 'dbi.uuid_detail', '=', 't.uuid_detail')
            ->leftJoin('p_bills as pbi', 'pbi.id', '=', 'dbi.bills_parent_id')
            ->leftJoin('transaction_bank_trans_d_s as dtbi', 'dtbi.uuid_detail_trans_bank', '=', 't.uuid_detail')
            ->leftJoin('transaction_bank_trans_p_s as ptbi', 'ptbi.id', '=', 'dtbi.trans_bank_parent_id')
            // ->Join('data_jamaah_xeros as dtj', 'dtj.uuid_contact', '=', 'ptbi.uuid_to')
            // ->Join('data_jamaah_xeros as dtj', 'dtj.uuid_contact', '=', 'pbi.uuid_from')
            // ->Join('data_jamaah_xeros as dtj', 'dtj.uuid_contact', '=', 'inv.contact_id')
            ->where('t.uuid_coa', $account)
            ->whereBetween('t.date_transaction', [$date_start, $date_end]);

        if (count($filterDivisi) > 0) {
            $query->where(function ($sub) use ($filterDivisi) {
                $sub->whereIn('di.divisi_travel_tracking_uuid', $filterDivisi)
                    ->orWhereIn('dbi.divisi_travel_tracking_uuid', $filterDivisi)
                    ->orWhereIn('dtbi.divisi_travel_tracking_uuid', $filterDivisi);
            });
        }

        if (count($filterPaket) > 0) {
            $query->where(function ($sub) use ($filterPaket) {
                $sub->whereIn('di.paket_tracking_uuid', $filterPaket)
                    ->orWhereIn('dbi.paket_tracking_uuid', $filterPaket)
                    ->orWhereIn('dtbi.paket_tracking_uuid', $filterPaket);
            });
        }

        $transactions = $query
            ->selectRaw('
            t.id,
            t.date_transaction,
            t.base_nominal   as nominal,
            t.is_speend,
            t.uuid_detail,
            inv.invoice_number,                 -- ⚠️ sesuaikan nama kolom no. invoice
            pbi.reference     as bill_reference, -- ⚠️ sesuaikan nama kolom no. bill
            ptbi.reference    as bank_reference,
            di.desc           as invoice_desc,   -- ⚠️ sesuaikan kalau nama kolomnya beda
            dbi.desc          as bill_desc,      -- ⚠️ sesuaikan kalau nama kolomnya beda
            dtbi.desc         as bank_desc,
            CASE
                WHEN di.id IS NOT NULL THEN \'invoice\'
                WHEN dbi.id IS NOT NULL THEN \'bill\'
                WHEN dtbi.id IS NOT NULL THEN \'bank\'
                ELSE NULL
            END as source
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
                'reference' => $t->invoice_number ?? $t->bill_reference ?? $t->bank_reference,
                'description' => $t->invoice_desc ?? $t->bill_desc ?? $t->bank_desc,
                'source' => $t->source,
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
            'date_end' => 'required|date|after_or_equal:date_start',
            'tracking_divisi' => 'nullable|array',
            'tracking_divisi.*' => 'nullable|string',
            'tracking_paket_name' => 'nullable|array',
            'tracking_paket_name.*' => 'nullable|string',
            'currency' => 'nullable|string|size:3',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 422);
        }

        $dateStart = Carbon::parse($request->date_start)->startOfDay();
        $dateEndExclusive = Carbon::parse($request->date_end)
            ->addDay()
            ->startOfDay();

        $filterDivisi = array_values(array_filter(
            (array) $request->input('tracking_divisi', [])
        ));

        $filterPaket = array_values(array_filter(
            (array) $request->input('tracking_paket_name', [])
        ));

        $hasFilterDivisi = !empty($filterDivisi);
        $hasFilterPaket = !empty($filterPaket);

        /*
         * Base query per sumber transaksi.
         * whereExists digunakan agar satu t.id tidak berlipat
         * akibat relasi detail yang memiliki banyak baris.
         */
        $makeSourceQuery = function ($detailTable, $detailUuidColumn) use ($dateStart, $dateEndExclusive, $filterDivisi, $filterPaket, $hasFilterDivisi, $hasFilterPaket) {
            $query = DB::table('transaction_all_coas as t')
                ->join('coas as c', 'c.id', '=', 't.uuid_coa')
                ->where('t.date_transaction', '>=', $dateStart)
                ->where('t.date_transaction', '<', $dateEndExclusive)
                ->whereExists(function ($sub) use ($detailTable, $detailUuidColumn, $filterDivisi, $filterPaket, $hasFilterDivisi, $hasFilterPaket) {
                    $sub->select(DB::raw(1))
                        ->from($detailTable . ' as d')
                        ->whereColumn(
                            'd.' . $detailUuidColumn,
                            't.uuid_detail'
                        );

                    if ($hasFilterDivisi) {
                        $sub->whereIn(
                            'd.divisi_travel_tracking_uuid',
                            $filterDivisi
                        );
                    }

                    if ($hasFilterPaket) {
                        $sub->whereIn(
                            'd.paket_tracking_uuid',
                            $filterPaket
                        );
                    }
                })
                ->select(
                    't.id as trx_row_id',
                    't.uuid_detail',
                    't.date_transaction',
                    't.is_speend',
                    't.base_nominal',
                    't.nominal_currency',
                    't.code_curr',
                    'c.id as coa_id',
                    'c.name as coa_name',
                    'c.account_type'
                );

            return $query;
        };

        // Invoice: Accounts Receivable (ACCREC)
        $invoiceQuery = $makeSourceQuery(
            'item_detail_invoices',
            'uuid_detail_inv'
        );

        // Bill: Accounts Payable (ACCPAY)
        $billQuery = $makeSourceQuery(
            'd_bills',
            'uuid_detail'
        );

        // Bank Transactions: Receive Money / Spend Money
        $bankQuery = $makeSourceQuery(
            'transaction_bank_trans_d_s',
            'uuid_detail_trans_bank'
        );

        /*
         * Gabungkan seluruh sumber.
         * UNION menghilangkan baris identik, sementara trx_row_id
         * mempertahankan transaksi berbeda walaupun nominal sama.
         */
        $baseQuery = $invoiceQuery
            ->union($billQuery)
            ->union($bankQuery);

        /*
         * Ambil bagian laporan sesuai account_type Xero.
         * Asumsi: is_speend = 1 berarti pengeluaran dengan
         * base_nominal tersimpan positif.
         */

        $getSection = function (array $accountTypes, bool $isExpense) use ($baseQuery) {

            $normalSpend = $isExpense ? 1 : 0;

            // Sama dengan profitAndLossDetailData:
            // arah normal = positif, arah sebaliknya = negatif, tanda base_nominal dipertahankan
            $amountExpression = "
        CASE
            WHEN unified.is_speend = ?
            THEN unified.base_nominal
            ELSE -unified.base_nominal
        END
    ";

            // Detail per COA
            $rows = DB::query()
                ->fromSub(clone $baseQuery, 'unified')
                ->whereIn('unified.account_type', $accountTypes)
                ->selectRaw("
            unified.coa_id as id,
            unified.coa_name as name,
            SUM($amountExpression) as total
        ", [$normalSpend])
                ->groupBy('unified.coa_id', 'unified.coa_name')
                ->orderBy('unified.coa_name')
                ->get();

            // Total section = jumlah dari baris per COA, supaya selalu konsisten
            $total = $rows->sum(function ($row) {
                return (float) $row->total;
            });

            return [
                'items' => $rows->map(function ($row) {
                    return [
                        'coa_id' => $row->id,
                        'name' => $row->name,
                        'total' => (float) $row->total,
                    ];
                })->values(),

                'total' => (float) $total,
            ];
        };


        // Mapping sesuai kategori Profit & Loss Xero
        $tradingIncome = $getSection(['REVENUE'], false);

        $costOfSales = $getSection(['DIRECTCOSTS'], true);

        $otherIncome = $getSection(['OTHERINCOME'], false);

        $operatingExpenses = $getSection(['EXPENSE'], true);

        // Perhitungan Profit & Loss
        $grossProfit = $tradingIncome['total']
            - $costOfSales['total'];

        $netProfit = $grossProfit
            + $otherIncome['total']
            - $operatingExpenses['total'];

        // Tracking labels
        $divisi = $this->tracing->getUUIDKategory(
            'Divisi',
            $filterDivisi
        );

        $paket = $this->tracing->getUUIDKategory(
            'Nama Paket',
            $filterPaket
        );

        $data = [
            'period' => [
                'date_start' => $dateStart->toDateString(),
                'date_end' => $dateEndExclusive
                    ->copy()
                    ->subDay()
                    ->toDateString(),
            ],

            'active_filters' => [
                'tracking_divisi' => $hasFilterDivisi
                    ? $filterDivisi : null,
                'tracking_paket_name' => $hasFilterPaket
                    ? $filterPaket : null,
                'divisi_name' => $divisi,
                'paket_name' => $paket,
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
