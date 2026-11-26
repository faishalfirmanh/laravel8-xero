<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Repository\Expenses\PODBillRepository;
use App\Http\Repository\MasterData\Finance\InvoiceAllXeroRepo;
use App\Http\Repository\Revenue\InvoiceDXeroLocalRepo;
use App\Http\Repository\Transaction\TransBankRepo;
use App\Http\Repository\Transaction\TransCoaRepo;
use App\Models\Expenses\Purchase\Bill\DBill;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;

use App\Http\Repository\MasterData\CoaRepo;


use Illuminate\Pagination\LengthAwarePaginator;
use Validator;
use App\Traits\ApiResponse;



class CoaController extends Controller
{

    use ApiResponse;
    protected $repo, $repo_trans_all, $repo_d_bill, $repo_trans_bank, $repo_invoice, $repo_detail_invoice;
    private $ticketCoaCodes = ['4100', '4112'];
    protected $labaCoaMap = [
        '4100' => ['HPP TIKET*'],    // pendapatan tiket  -> dikurangi HPP TIKET ...
        '4112' => ['HPP HOTEL*'],    // pendapatan hotel  -> dikurangi HPP HOTEL ...
    ];

    public function __construct(
        CoaRepo $repo,
        TransCoaRepo $transCoaRepo,
        PODBillRepository $repo_d_bill,
        TransBankRepo $repo_trans_bank,
        InvoiceAllXeroRepo $repo_invoice,
        InvoiceDXeroLocalRepo $repo_detail_invoice
    ) {
        $this->repo = $repo;
        $this->repo_trans_all = $transCoaRepo;
        $this->repo_d_bill = $repo_d_bill;
        $this->repo_trans_bank = $repo_trans_bank;
        $this->repo_invoice = $repo_invoice;
        $this->repo_detail_invoice = $repo_detail_invoice;
    }

    function generateRandom4Digit()
    {
        return str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    }


    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            // 'account_type' => 'required|string|regex:/^(current_asset|fixed_asset|revenue|inventory|non_current_asset|prepayment|equity|description|direct_cost|expense|overhead|current_liability|liability|non_current_liability|other_income|sales)$/i',
            'account_type' => [
                'required',
                'string',
                'regex:/^(CURRENT|FIXED|EQUITY|EXPENSE|DIRECTCOSTS|CURRLIAB|OTHERINCOME|REVENUE)$/i'
            ],
            // 'code' => [
            //     'required',
            //     'string',
            //      Rule::unique('coas', 'code')->ignore($request->id)
            // ],
            'name' => 'required|string',
            'desc' => 'nullable|string'

        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors());
        }

        if ($request->id == NULL) {
            $request->merge(['code' => self::generateRandom4Digit()]);
        }

        $request['created_by'] = $request->user_login->id;
        $saved = $this->repo->CreateOrUpdate($request->all(), $request->id);
        return $this->autoResponse($saved);
    }

    private function normalizeList($raw): array
    {
        return collect(is_array($raw) ? $raw : [$raw])
            ->filter(fn($v) => is_scalar($v))
            ->map(fn($v) => trim((string) $v))
            ->filter(fn($v) => $v !== '')
            ->unique()
            ->values()
            ->all();
    }

    public function getAllPaginateSelect2(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'page' => 'required|integer',
            'keyword' => 'nullable|string',
            'type' => 'nullable'
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 404);
        }
        //dd($request->menu);
        // $where = $request->type ? ['account_type' => $request->type] : [];
        $types = $request->type;
        if (is_string($types)) {
            $types = explode(',', $types);
        }
        $types = array_values(array_filter(array_map('trim', (array) $types)));

        $where = !empty($types) ? ['account_type' => $types] : [];

        if ($request->keyword != null) {
            $data = $this->repo->searchData($where, 10, $request->page, 'name', strtoupper($request->keyword));
        } else {
            $data = $this->repo->getAllDataWithDefault($where, 10, $request->page, 'name', 'ASC');//getDataPaginate("name",10,$request->keyword);
        }
        //$data['menu']= $request->menu;
        return $this->autoResponse($data);
        //return $this->success($data);
    }

    public function getAllPaginate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'page' => 'required|integer',
            'keyword' => 'nullable|string',
            'kolom_name' => 'required|string',
            'limit' => 'required|integer',
            'type' => 'nullable|string|in:EXPENSE,REVENUE,ASSET,ALL,LIABILITIES,EQUITY'
        ]);


        if ($validator->fails()) {
            return $this->error($validator->errors(), 404);
        }
        //dd($request->menu);
        $searchColumns = [
            'code',
            'name',   // accessor inv_number
            'desc',     // accessor name_contact
            // accessor bank_name
        ];
        $where = self::chekWhere($request->type);
        if ($request->keyword != null) {
            $data = $this->repo->searchMultiColumnV2($where, $request->limit, $request->page, strtoupper($request->keyword), $searchColumns);
        } else {
            $data = $this->repo->getAllDataWithDefault($where, $request->limit, $request->page, $request->kolom_name, 'ASC');//getDataPaginate("name",10,$request->keyword);
        }

        return $this->autoResponse($data);

    }

    public function getCoaBycode($code)
    {
        $data = $this->repo->whereData(['code' => $code])->first();
        return $this->autoResponse($data);
    }

    public function chekWhere($requestType)
    {
        // Jika ALL, kembalikan array kosong (agar ->where([]) tidak memfilter apapun)
        if ($requestType == 'ALL' || empty($requestType)) {
            return [];
        }

        // Kembalikan Closure berisi Query Builder
        return function ($query) use ($requestType) {
            if ($requestType == 'ASSET') {
                $query->whereIn('account_type', ['CURRENT', 'FIXED', 'BANK']);
            } else if ($requestType == 'LIABILITIES') {
                $query->where('account_type', 'CURRLIAB');
            } else if ($requestType == 'EQUITY') {
                $query->where('account_type', 'EQUITY');
            } else if ($requestType == 'EXPENSE') {
                // Catatan: Saya ubah 'EQUITY' menjadi 'EXPENSE' karena sepertinya di script asli Anda ada typo
                $query->whereIn('account_type', ['EXPENSE', 'DIRECTCOSTS']);
            } else if ($requestType == 'REVENUE') {
                $query->whereIn('account_type', ['OTHERINCOME', 'REVENUE']);
            }
        };
    }

    public function getListTransByCoaId(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code_coa' => 'required|exists:coas,id',
            'page' => 'required|integer',
            'limit' => 'required|integer',
            'keyword' => 'nullable|string',
            'date_start' => 'nullable|date',
            'date_end' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 404);
        }

        $where = ['uuid_coa' => $request->code_coa];
        $relations = ['d_bill', 'd_bill.getParent', 'd_bank', 'd_bank.getParent', 'd_invoice', 'd_invoice.getParent', 'd_feeOverpay'];

        $search_columns = [
            'nominal',
            'date_transaction' => 'date',

            'd_bill' => ['desc'],
            'd_bill.getParent.getContactFrom' => ['full_name'],

            'd_bank' => ['desc'],
            'd_bank.getParent.getContactFrom' => ['full_name'],

            'd_invoice' => ['desc'],
            'd_invoice.getParent' => ['contact_name'],
        ];

        // ── DATE RANGE FILTER ──
        $dateRange = [
            'column' => 'date_transaction',
            'start' => $request->date_start,
            'end' => $request->date_end,
        ];

        if ($request->keyword) {
            $data = $this->repo_trans_all->searchDataMultiColumn(
                $where,
                $request->limit,
                $search_columns,
                $request->keyword,
                $relations,
                $dateRange
            );
        } else {
            $data = $this->repo_trans_all->getAllDataWithDefault(
                $where,
                $request->limit,
                $request->page,
                'date_transaction',
                'ASC',
                $relations,
                $dateRange
            );
        }

        return $this->autoResponse($data);
    }





    /** Halaman blade: hanya kirim opsi COA. Data diambil lewat AJAX. */
    public function viewCoaTrans()
    {
        return view('admin.report.payment_coa', [
            'coaOptions' => $this->repo->getByCodes($this->ticketCoaCodes),
            'today' => Carbon::today()->toDateString(),
        ]);
    }

    /**
     * 1. closing ->langsung lunas keterangan : closing - lunas
     * 2. jika filter due dan start sama : 1 hari
     * 3. belum lunas ambil dari : transaction_nominal_bank_accounts
     * 4. invoice yang due_date-nya sudah lewat dan belum lunas tidak tampil
     * 5 closing & pembayaran : tetap pakai transaction_nominal_bank_accounts, harus lunas pake invoice
     * Param: page, limit, kolom_name, keyword (opsional: cari no invoice / nama kontak),
     *        coa_code (4100|4112, default 4100), date_from, date_to (default hari ini),
     *        status (bayar|dp|pelunasan|closing, default bayar)
     */

    /**
     * Tempel semua method di bawah ini ke dalam class controller Anda.
     *
     * - getCoaTrans         : GANTI yang lama (tambah status "all")
     * - getCoaTransSummary  : GANTI yang lama (tambah all_count + laba_bersih)
     * - buildTransRows, tagRows, buildNetProfit, groupProfit : method private BARU
     *
     * Tidak perlu `use` baru (Carbon dipanggil lewat \Carbon\Carbon).
     */

    /**
     * GET daftar transaksi per COA.
     *
     * status : closing | pelunasan | segera | all   (default: closing)
     *          all = gabungan closing + pelunasan + segera.
     *          Setiap baris diberi field `tx_status` (closing / pelunasan / segera)
     *          supaya kolom STATUS di tabel bisa ditampilkan.
     */
    public function getCoaTrans(Request $request, $coaCode = null)
    {
        $validator = Validator::make($request->all(), [
            'page' => 'required|integer',
            'limit' => 'required|integer',
            'kolom_name' => 'required|string',
            'keyword' => 'nullable|string',
            'coa_code' => 'nullable|in:' . implode(',', $this->ticketCoaCodes),
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'status' => 'nullable|in:closing,pelunasan,segera,all',
            'include_overdue' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 404);
        }

        $coa = $this->resolveTicketCoa($request, $coaCode);
        if (!$coa) {
            return $this->error('COA tidak ditemukan', 404);
        }

        list($dateFrom, $dateTo) = $this->ticketDateRange($request);

        $data = $this->buildTransRows(
            (int) $coa->id,
            $dateFrom,
            $dateTo,
            $request->input('status', 'closing'),
            $request->boolean('include_overdue')
        );

        if ($request->filled('keyword')) {
            $kw = $request->keyword;
            $data = $data->filter(function ($r) use ($kw) {
                return mb_stripos($r->invoice_number, $kw) !== false
                    || mb_stripos((string) $r->contact_name, $kw) !== false;
            });
        }

        $data = $data->values();
        $page = max((int) $request->page, 1);
        $limit = max((int) $request->limit, 1);

        $paginator = new LengthAwarePaginator(
            $data->forPage($page, $limit)->values(),
            $data->count(),
            $limit,
            $page
        );

        return $this->autoResponse($paginator);
    }

    /*
     * Pengganti getCoaTransSummary + buildNetProfit di CoaTransController.methods.php.
     * HAPUS method lama: buildNetProfit() dan groupProfit() (tidak dipakai lagi),
     * dan TIDAK PERLU getProfitRowsByCoa() di repo_invoice.
     *
     * Prasyarat: class ini bisa memanggil normalizeList(), buildDetailFilterUnion() dan
     * queryPlRows() milik laporan Profit & Loss. Jika method itu ada di controller P&L,
     * pindahkan ketiganya (potong-tempel, isi tidak berubah) ke trait, mis.
     * App\Http\Controllers\Concerns\ProfitLossQueries, lalu `use ProfitLossQueries;`
     * di kedua controller.
     *
     * Perlu di atas file controller:
     *   use Carbon\Carbon;
     *   use Illuminate\Support\Facades\DB;
     */

    /**
     * GET ringkasan per COA.
     *
     * laba_bersih=1 -> sertakan key `laba_bersih` (default tidak dihitung, jadi ringan).
     * Filter laba (sama seperti halaman P&L, semuanya opsional):
     *   nama_paket[], tracking_divisi[], tracking_paket_name[]
     */
    public function getCoaTransSummary(Request $request, $coaCode = null)
    {
        $validator = Validator::make($request->all(), [
            'coa_code' => 'nullable|in:' . implode(',', $this->ticketCoaCodes),
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'include_overdue' => 'nullable|boolean',
            'laba_bersih' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 404);
        }

        $coa = $this->resolveTicketCoa($request, $coaCode);
        if (!$coa) {
            return $this->error('COA tidak ditemukan', 404);
        }

        list($dateFrom, $dateTo) = $this->ticketDateRange($request);

        $rows = $this->buildPaymentRows((int) $coa->id, $dateFrom, $dateTo);

        // uang masuk = tanpa pembayaran via overpay (apply credit), karena uangnya sudah dicatat saat diterima
        $cash = function ($items) {
            return $items->where('via_overpay', false)->sum('amount_base');
        };
        $closing = $rows->whereIn('kind', ['dp', 'lunas_langsung']);
        $pelunas = $rows->where('kind', 'pelunasan');

        $due = collect($this->repo_invoice->getDueUnpaidByCoa(
            (int) $coa->id,
            $dateFrom,
            $dateTo,
            $request->boolean('include_overdue')
        ));

        return response()->json([
            'status' => true,
            'data' => [
                'all_count' => $closing->count() + $pelunas->count() + $due->count(),
                'closing_count' => $closing->count(),
                'closing_cash' => $cash($closing),
                'pelunasan_count' => $pelunas->count(),
                'pelunasan_cash' => $cash($pelunas),
                'segera_count' => $due->count(),
                'segera_sisa' => $due->sum('sisa_base'),
                'laba_bersih' => $request->boolean('laba_bersih')
                    ? $this->buildNetProfit($coa, $dateFrom, $dateTo)
                    : null,
            ],
        ]);
    }


    /**
     * Laba bersih memakai rumus & query yang sama dengan getProfitAndLostV3Combine:
     *   laba = REVENUE - DIRECTCOSTS + OTHERINCOME - EXPENSE
     * difilter oleh item (nama_paket) dan/atau tracking (divisi, paket) lewat
     * buildDetailFilterUnion, jadi angkanya cocok dengan halaman Profit & Loss.
     *
     * Efisiensi: hanya 1 query queryPlRows (tanpa kolom per paket), 1 query kode item
     * (hanya jika filter item dipakai), dan satu kali loop di memori.
     * getUUIDKategory tidak dipanggil karena label tracking tidak dibutuhkan di sini.
     */

    private function queryPlRows(?Carbon $dateStart, ?Carbon $dateEndExclusive, $detailFilter)
    {
        $query = DB::table('transaction_all_coas as t')
            ->join('coas as c', 'c.id', '=', 't.uuid_coa')
            ->whereIn('c.account_type', ['REVENUE', 'DIRECTCOSTS', 'OTHERINCOME', 'EXPENSE']);

        // Filter tanggal hanya jika keduanya tersedia
        if ($dateStart !== null && $dateEndExclusive !== null) {
            $query->where('t.date_transaction', '>=', $dateStart->toDateTimeString())
                ->where('t.date_transaction', '<', $dateEndExclusive->toDateTimeString());
        }

        if ($detailFilter !== null) {
            $query->joinSub($detailFilter, 'f', 'f.uuid_detail', '=', 't.uuid_detail');
        }

        return $query
            ->selectRaw("
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
            ->groupBy('c.id', 'c.name', 'c.account_type')
            ->orderBy('c.name')
            ->get();
    }

    private function buildTransRows(int $coaId, $dateFrom, $dateTo, string $status, bool $includeOverdue)
    {
        $payments = $status === 'segera'
            ? collect()
            : $this->buildPaymentRows($coaId, $dateFrom, $dateTo);

        $rows = collect();

        if (in_array($status, ['closing', 'all'], true)) {
            $rows = $rows->concat($this->tagRows(
                $payments->whereIn('kind', ['dp', 'lunas_langsung']),
                'closing'
            ));
        }

        if (in_array($status, ['pelunasan', 'all'], true)) {
            $rows = $rows->concat($this->tagRows(
                $payments->where('kind', 'pelunasan'),
                'pelunasan'
            ));
        }

        if (in_array($status, ['segera', 'all'], true)) {
            $due = collect($this->repo_invoice->getDueUnpaidByCoa(
                $coaId,
                $dateFrom,
                $dateTo,
                $includeOverdue
            ))->map(function ($r) {
                $r->mixed = (int) $r->other_lines > 0;
                $r->wa_number = $this->normalizeWaNumber($r->phone_number);
                unset($r->phone_number);
                return $r;
            });

            $rows = $rows->concat($this->tagRows($due, 'segera'));
        }

        return $rows;
    }


    private function tagRows($rows, string $txStatus)
    {
        return $rows->map(function ($r) use ($txStatus) {
            $r->tx_status = $txStatus;
            return $r;
        })->values();
    }

    private function buildDetailFilterUnion(array $paketIds, array $paketCodes, array $divisi, array $trackPaket)
    {
        $hasItem = !empty($paketIds);
        $hasTracking = !empty($divisi) || !empty($trackPaket);

        if (!$hasItem && !$hasTracking) {
            return null;
        }

        $tracking = function ($q) use ($divisi, $trackPaket) {
            if (!empty($divisi)) {
                $q->whereIn('divisi_travel_tracking_uuid', $divisi);
            }
            if (!empty($trackPaket)) {
                $q->whereIn('paket_tracking_uuid', $trackPaket);
            }
            return $q;
        };

        $sources = [];

        // ── Kelompok ITEM ────────────────────────────────────────
        if ($hasItem) {
            $sources[] = DB::table('item_detail_invoices')
                ->select('uuid_detail_inv as uuid_detail')
                ->distinct()
                ->whereIn('item_id', $paketIds);

            $sources[] = DB::table('d_bills')
                ->select('uuid_detail')
                ->distinct()
                ->whereIn('item_id_prod_serv', $paketIds);

            if (!empty($paketCodes)) {
                $sources[] = DB::table('d_bills')
                    ->select('uuid_detail')
                    ->distinct()
                    ->whereIn('item_code', $paketCodes);
            }

            $sources[] = DB::table('transaction_bank_trans_d_s')
                ->select('uuid_detail_trans_bank as uuid_detail')
                ->distinct()
                ->whereIn('item_code', $paketIds);
        }

        // ── Kelompok TRACKING ────────────────────────────────────
        if ($hasTracking) {
            $sources[] = $tracking(
                DB::table('item_detail_invoices')->select('uuid_detail_inv as uuid_detail')->distinct()
            );
            $sources[] = $tracking(
                DB::table('d_bills')->select('uuid_detail')->distinct()
            );
            $sources[] = $tracking(
                DB::table('transaction_bank_trans_d_s')->select('uuid_detail_trans_bank as uuid_detail')->distinct()
            );
        }

        $union = array_shift($sources);
        foreach ($sources as $s) {
            $union->union($s); // union() di Laravel sudah DISTINCT by default
        }

        return $union;
    }
    private function buildNetProfit($coa, $dateFrom, $dateTo): array
    {
        $coaId = (int) $coa->id;
        $coaCode = (string) data_get($coa, 'code');
        $hppMap = $this->labaCoaMap[$coaCode] ?? [];

        if (empty($hppMap)) {
            return [
                'mapping_ok' => false,
                'pesan' => 'Mapping COA HPP untuk ' . $coaCode . ' belum diatur (lihat $labaCoaMap di controller).',
            ];
        }

        $start = $dateFrom ? Carbon::parse($dateFrom)->startOfDay() : null;
        $endExclusive = $dateTo ? Carbon::parse($dateTo)->addDay()->startOfDay() : null;

        $rows = $this->queryCoaDaily($coaId, $hppMap, $start, $endExclusive);
        $qty = $this->queryRevenueQtyDaily($coaId, $start, $endExclusive);

        $result = $this->aggregateNetProfit($rows, $qty);

        return array_merge([
            'mapping_ok' => true,
            'scope_label' => 'COA ' . $coaCode . ' dikurangi ' . implode(', ', array_map('strval', $hppMap)),
        ], $result);
    }

    private function aggregateNetProfit(array $rows, array $qty): array
    {
        $days = [];   // d => [rev, hpp]
        $coas = [];

        foreach ($rows as $r) {
            $isRev = $r->account_type === 'REVENUE';
            $t = (float) $r->total;
            $d = (string) $r->d;

            $days[$d] = $days[$d] ?? ['rev' => 0.0, 'hpp' => 0.0];
            $days[$d][$isRev ? 'rev' : 'hpp'] += $t;

            $k = $r->coa_id;
            if (!isset($coas[$k])) {
                $coas[$k] = [
                    'order' => $isRev ? 1 : 2,
                    'coa_id' => $r->coa_id,
                    'name' => $r->coa_name,
                    'type' => $isRev ? 'Pendapatan' : 'HPP',
                    'amount' => 0.0,
                    'selected' => $isRev,
                ];
            }
            $coas[$k]['amount'] += $isRev ? $t : -$t;
        }

        foreach ($qty as $d => $q) {
            $days[$d] = $days[$d] ?? ['rev' => 0.0, 'hpp' => 0.0];
        }
        ksort($days);

        $add = function (array &$bucket, string $key, array $meta, float $rev, float $hpp, float $q) {
            if (!isset($bucket[$key])) {
                $bucket[$key] = $meta + ['periode' => $key, 'pendapatan' => 0.0, 'hpp' => 0.0, 'pax' => 0.0];
            }
            $bucket[$key]['pendapatan'] += $rev;
            $bucket[$key]['hpp'] += $hpp;
            $bucket[$key]['pax'] += $q;
        };

        $weeks = [];
        $months = [];
        $rev = $hpp = $pax = 0.0;

        foreach ($days as $d => $v) {
            $q = (float) ($qty[$d] ?? 0);
            $dt = new \DateTimeImmutable($d);
            $ws = $dt->modify('monday this week');

            $rev += $v['rev'];
            $hpp += $v['hpp'];
            $pax += $q;

            $add(
                $weeks,
                $dt->format('o-\WW'),
                ['awal' => $ws->format('Y-m-d'), 'akhir' => $ws->modify('+6 days')->format('Y-m-d')],
                $v['rev'],
                $v['hpp'],
                $q
            );
            $add($months, $dt->format('Y-m'), [], $v['rev'], $v['hpp'], $q);
        }

        $finish = function (array $bucket) {
            return array_values(array_map(function ($b) {
                $b['laba'] = $b['pendapatan'] - $b['hpp'];
                $b['per_orang'] = $b['pax'] > 0 ? round($b['laba'] / $b['pax'], 2) : 0;
                $b['pax'] = (int) round($b['pax']);
                return $b;
            }, $bucket));
        };

        usort($coas, function ($a, $b) {
            return [$a['order'], mb_strtolower($a['name'])] <=> [$b['order'], mb_strtolower($b['name'])];
        });

        $laba = $rev - $hpp;

        return [
            'pendapatan' => $rev,
            'hpp' => $hpp,
            'laba' => $laba,
            'pax' => (int) round($pax),
            'per_orang' => $pax > 0 ? round($laba / $pax, 2) : 0,
            'per_minggu' => $finish($weeks),
            'per_bulan' => $finish($months),
            'per_coa' => array_values($coas),
        ];
    }

    private function queryRevenueQtyDaily(int $coaId, $start, $endExclusive): array
    {
        $makeRevenueRows = function () use ($coaId, $start, $endExclusive) {
            $q = DB::table('transaction_all_coas as t')->where('t.uuid_coa', $coaId);

            if ($start !== null && $endExclusive !== null) {
                $q->where('t.date_transaction', '>=', $start->toDateTimeString())
                    ->where('t.date_transaction', '<', $endExclusive->toDateTimeString());
            }

            return $q->selectRaw('t.uuid_detail, t.is_speend, MIN(DATE(t.date_transaction)) as d')
                ->groupBy('t.uuid_detail', 't.is_speend');
        };

        $out = [];
        foreach ([
            ['item_detail_invoices', 'uuid_detail_inv'],
            ['transaction_bank_trans_d_s', 'uuid_detail_trans_bank'],
        ] as $src) {
            $rows = DB::query()
                ->fromSub($makeRevenueRows(), 'r')
                ->join($src[0] . ' as q', 'q.' . $src[1], '=', 'r.uuid_detail')
                ->selectRaw('r.d, SUM(CASE WHEN r.is_speend = 0 THEN q.qty ELSE -q.qty END) as qty')
                ->groupBy('r.d')
                ->get();

            foreach ($rows as $x) {
                $out[$x->d] = ($out[$x->d] ?? 0.0) + (float) $x->qty;
            }
        }

        return $out;
    }


    private function queryCoaDaily(int $coaId, array $hppMap, $start, $endExclusive): array
    {
        $hppIds = array_map('intval', array_filter($hppMap, 'is_int'));
        $hppLikes = array_map([$this, 'wildcardToLike'], array_values(array_filter($hppMap, 'is_string')));

        $query = DB::table('transaction_all_coas as t')
            ->join('coas as c', 'c.id', '=', 't.uuid_coa')
            ->where(function ($w) use ($coaId, $hppIds, $hppLikes) {
                $w->where(function ($r) use ($coaId) {
                    $r->where('c.id', $coaId)->where('c.account_type', 'REVENUE');
                })->orWhere(function ($h) use ($hppIds, $hppLikes) {
                    // HPP bisa terklasifikasi Cost of Sales atau Expense, dua-duanya pengurang
                    $h->whereIn('c.account_type', ['DIRECTCOSTS', 'EXPENSE'])
                        ->where(function ($n) use ($hppIds, $hppLikes) {
                        if ($hppIds) {
                            $n->orWhereIn('c.id', $hppIds);
                        }
                        foreach ($hppLikes as $like) {
                            $n->orWhereRaw('LOWER(c.name) LIKE ?', [$like]);
                        }
                    });
                });
            });

        if ($start !== null && $endExclusive !== null) {
            $query->where('t.date_transaction', '>=', $start->toDateTimeString())
                ->where('t.date_transaction', '<', $endExclusive->toDateTimeString());
        }

        return $query->selectRaw("
                c.id           as coa_id,
                c.name         as coa_name,
                c.account_type as account_type,
                DATE(t.date_transaction) as d,
                SUM(
                    CASE
                        WHEN c.account_type IN ('REVENUE', 'OTHERINCOME')
                            THEN CASE WHEN t.is_speend = 0 THEN t.base_nominal ELSE -t.base_nominal END
                        ELSE CASE WHEN t.is_speend = 1 THEN t.base_nominal ELSE -t.base_nominal END
                    END
                ) as total
            ")
            ->groupBy('c.id', 'c.name', 'c.account_type', DB::raw('DATE(t.date_transaction)'))
            ->get()
            ->all();
    }

    private function wildcardToLike(string $pattern): string
    {
        $p = mb_strtolower(trim($pattern));
        $p = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $p);
        return str_replace('*', '%', $p);
    }
    private function resolveTicketCoa(Request $request, $coaCode = null)
    {
        $coaCode = (string) ($request->input('coa_code') ?: ($coaCode ?: '4100'));

        return $this->repo->getByCodes($this->ticketCoaCodes)->first(function ($c) use ($coaCode) {
            return (string) $c->code === $coaCode;
        });
    }

    private function ticketDateRange(Request $request)
    {
        $today = Carbon::today()->toDateString();
        $from = $request->filled('date_from') ? Carbon::parse($request->date_from)->toDateString() : $today;
        $to = $request->filled('date_to') ? Carbon::parse($request->date_to)->toDateString() : $from;

        return [$from, $to];
    }

    private function buildPaymentRows(int $coaId, string $dateFrom, string $dateTo)
    {
        return collect($this->repo_trans_bank->getTicketPaymentReport($coaId, $dateFrom, $dateTo))
            ->map(function ($r) {
                return $this->classifyTicketPayment($r);
            });
    }

    /**
     * Jenis pembayaran:
     *  dp             : pembayaran pertama invoice, belum lunas        (closing / dp masuk)
     *  lunas_langsung : pembayaran pertama langsung melunasi            (closing / dp masuk, label "closing-lunas")
     *  pelunasan      : pembayaran ke-2 dst yang membuat invoice lunas  (baru pelunasan)
     *  cicilan        : pembayaran ke-2 dst yang belum melunasi         (tidak tampil di 3 filter)
     * Lunas = total pembayaran berjalan (urut date_transaction, id) >= invoice_total.
     */
    private function classifyTicketPayment($r)
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
    private function normalizeWaNumber($phone)
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

    public function delete(Request $request)
    {
        $id = $request->id;

        $validator = Validator::make($request->all(), [
            'id' => 'required|numeric|exists:coas,id',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 404);
        }

        $cekDbill = $this->repo_d_bill->whereData(['account_id_coa' => $request->id])->first();
        if ($cekDbill) {
            return $this->error("coa sedang di gunakan pada bills", 407, "failed deleted");
        }

        $blog = $this->repo->find($id);

        if ($blog) {
            $data = $this->repo->delete($id);
            return $this->autoResponse($data);
        }

        return $this->error('hotel not found', 404);
    }


    public function detail(Request $request)
    {
        $data = $this->repo->find($request->id);
        return $this->autoResponse($data);
    }

    //
}
