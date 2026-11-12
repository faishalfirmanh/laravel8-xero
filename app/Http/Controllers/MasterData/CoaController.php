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
            'status' => 'nullable|in:closing,pelunasan,segera',
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
        $status = $request->input('status', 'closing');

        if ($status === 'segera') {
            $data = collect($this->repo_invoice->getDueUnpaidByCoa(
                (int) $coa->id,
                $dateFrom,
                $dateTo,
                $request->boolean('include_overdue')
            ))->map(function ($r) {
                $r->mixed = (int) $r->other_lines > 0;
                $r->wa_number = $this->normalizeWaNumber($r->phone_number);
                unset($r->phone_number);
                return $r;
            });
        } else {
            $data = $this->buildPaymentRows((int) $coa->id, $dateFrom, $dateTo);
            $data = $status === 'pelunasan'
                ? $data->where('kind', 'pelunasan')
                : $data->whereIn('kind', ['dp', 'lunas_langsung']); // closing (dp masuk)
        }

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

    /** Ringkasan 3 kartu (tidak ikut filter status / keyword). */
    public function getCoaTransSummary(Request $request, $coaCode = null)
    {
        $validator = Validator::make($request->all(), [
            'coa_code' => 'nullable|in:' . implode(',', $this->ticketCoaCodes),
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
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
                'closing_count' => $closing->count(),
                'closing_cash' => $cash($closing),
                'pelunasan_count' => $pelunas->count(),
                'pelunasan_cash' => $cash($pelunas),
                'segera_count' => $due->count(),
                'segera_sisa' => $due->sum('sisa_base'),
            ],
        ]);
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
