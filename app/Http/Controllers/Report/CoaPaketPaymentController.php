<?php

namespace App\Http\Controllers\Report; // sesuaikan namespace / folder controller Anda

use App\Http\Controllers\Controller;
use App\Http\Repository\MasterData\CoaRepo;
use App\Http\Repository\MasterData\Finance\InvoiceAllXeroRepo;
use App\Http\Repository\Transaction\TransBankRepo;
use App\Traits\ApiResponse;
use App\Traits\PaymentReportHelper;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Validator;
// use App\Traits\ApiResponse;            // sesuaikan namespace trait ApiResponse milik project
// use App\Traits\PaymentReportHelper;    // file 2_PaymentReportHelper.php
// use App\Http\Repository\...\CoaRepo; TransBankRepo; InvoiceAllXeroRepo;  // sesuaikan namespace repo

// =====================================================================
// Route:
//   web.php : Route::get('report/coa-paket-payment', [CoaPaketPaymentController::class, 'viewCoaPaketPayment'])->name('view-report-coa-paket-payment');
//   api.php : Route::get('coa-paket-payment', [CoaPaketPaymentController::class, 'getCoaPaketPayment'])->name('get-coa-paket-payment');
//             Route::get('coa-paket-payment-summary', [CoaPaketPaymentController::class, 'getCoaPaketPaymentSummary'])->name('get-coa-paket-payment-summary');
// (list-paket-select2 dan tracking-by-parent sudah ada, dipakai apa adanya)
// =====================================================================

class CoaPaketPaymentController extends Controller
{
    use ApiResponse, PaymentReportHelper;

    private $coaCodes = ['4600', '4610', '4620', '4630'];

    protected $repo, $repo_trans_bank, $repo_invoice;

    public function __construct(
        CoaRepo $repo,
        TransBankRepo $repo_trans_bank,
        InvoiceAllXeroRepo $repo_invoice
    ) {
        $this->repo = $repo;
        $this->repo_trans_bank = $repo_trans_bank;
        $this->repo_invoice = $repo_invoice;
    }

    /** Halaman blade: hanya kirim opsi COA. Data lewat AJAX. */
    public function viewCoaPaketPayment()
    {
        return view('admin.report.coa_paket_payment', [
            'coaOptions' => $this->repo->getByCodes($this->coaCodes),
            'today' => Carbon::today()->toDateString(),
        ]);
    }

    /**
     * Data tabel (server-side paginate, format sama dengan getAllPaginate).
     * Param: page, limit, kolom_name, keyword (no invoice / nama kontak),
     *        coa_code (4600|4610|4620|4630, wajib),
     *        item_ids (id items_paket_all_from_xeros, array atau CSV) dan/atau
     *        tracking_uuids (item_uuid_category, array atau CSV)  -> wajib salah satu, digabung OR,
     *        date_from, date_to (default hari ini),
     *        status : closing | pelunasan | segera (default closing),
     *        include_overdue : 1 = status segera ikut yang sudah lewat jatuh tempo
     */
    public function getCoaPaketPayment(Request $request)
    {
        $validator = $this->filterValidator($request, [
            'page' => 'required|integer',
            'limit' => 'required|integer',
            'kolom_name' => 'required|string',
            'keyword' => 'nullable|string',
            'status' => 'nullable|in:closing,pelunasan,segera',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 404);
        }

        $ctx = $this->resolveContext($request);
        if ($ctx === null) {
            return $this->error('COA tidak ditemukan', 404);
        }

        $status = $request->input('status', 'closing');

        if ($status === 'segera') {
            $data = $this->dueInvoices($ctx, $request->boolean('include_overdue'));
        } else {
            $data = $this->paymentRows($ctx);
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
    public function getCoaPaketPaymentSummary(Request $request)
    {
        $validator = $this->filterValidator($request);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 404);
        }

        $ctx = $this->resolveContext($request);
        if ($ctx === null) {
            return $this->error('COA tidak ditemukan', 404);
        }

        $rows = $this->paymentRows($ctx);

        // uang masuk = tanpa pembayaran via overpay (apply credit)
        $cash = function ($items) {
            return $items->where('via_overpay', false)->sum('amount_base');
        };
        $closing = $rows->whereIn('kind', ['dp', 'lunas_langsung']);
        $pelunas = $rows->where('kind', 'pelunasan');
        $due = $this->dueInvoices($ctx, $request->boolean('include_overdue'));

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

    /**
     * Validasi filter bersama. item_ids / tracking_uuids boleh array (item_ids[]=1) atau CSV ("1,2"),
     * lalu dinormalkan jadi array. Salah satu wajib terisi.
     */
    private function filterValidator(Request $request, array $extra = [])
    {
        $request->merge([
            'item_ids' => $this->csvToList($request->input('item_ids')),
            'tracking_uuids' => $this->csvToList($request->input('tracking_uuids')),
        ]);

        $msg = 'Pilih nama paket atau tracking paket (salah satu wajib diisi)';

        return Validator::make($request->all(), array_merge([
            'coa_code' => 'required|in:' . implode(',', $this->coaCodes),
            'item_ids' => 'required_without:tracking_uuids|array',
            'item_ids.*' => 'integer',
            'tracking_uuids' => 'required_without:item_ids|array',
            'tracking_uuids.*' => 'string|max:100',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'include_overdue' => 'nullable|boolean',
        ], $extra), [
            'item_ids.required_without' => $msg,
            'tracking_uuids.required_without' => $msg,
        ]);
    }

    private function resolveContext(Request $request)
    {
        $coaCode = (string) $request->input('coa_code');
        $coa = $this->repo->getByCodes($this->coaCodes)->first(function ($c) use ($coaCode) {
            return (string) $c->code === $coaCode;
        });
        if (!$coa) {
            return null;
        }

        list($from, $to) = $this->reportDateRange($request);

        return [
            'coa_id' => (int) $coa->id,
            'items' => array_map('intval', $request->input('item_ids', [])),
            'tracking' => array_values($request->input('tracking_uuids', [])),
            'from' => $from,
            'to' => $to,
        ];
    }

    private function paymentRows(array $ctx)
    {
        return collect($this->repo_trans_bank->getPaketPaymentReport(
            $ctx['coa_id'],
            $ctx['items'],
            $ctx['tracking'],
            $ctx['from'],
            $ctx['to']
        ))->map(function ($r) {
            return $this->classifyPaymentRow($r);
        });
    }

    private function dueInvoices(array $ctx, bool $includeOverdue)
    {
        return collect($this->repo_invoice->getDueUnpaidByPaket(
            $ctx['coa_id'],
            $ctx['items'],
            $ctx['tracking'],
            $ctx['from'],
            $ctx['to'],
            $includeOverdue
        ))->map(function ($r) {
            $r->mixed = (int) $r->other_lines > 0;
            $r->wa_number = $this->normalizeWaNumber($r->phone_number);
            unset($r->phone_number);
            return $r;
        });
    }
}