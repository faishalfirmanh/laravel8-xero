<?php
namespace App\Http\Controllers\Transaction\Sales;

use App\Http\Controllers\Controller;
use App\Http\Repository\Revenue\InvoiceDXeroLocalRepo;
use App\Http\Repository\Transaction\OverPayRepo;
use App\Http\Repository\Transaction\TransBankRepo;
use App\Http\Repository\Transaction\TransCoaRepo;
use App\Models\MasterData\MasterCurrency;
use Cache;
use Illuminate\Http\Request;
use App\Http\Repository\Revenue\InvoiceXeroLocalRepo;
use App\Http\Repository\MasterData\DataJamaahXeroRepository;
use App\Http\Repository\Revenue\HotelDetailInvoicesRepository;
use Validator;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\Auth;
use App\Services\GlobalService;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\PaymentParams;
use Illuminate\Support\Facades\Http;
use App\ConfigRefreshXero;
use App\Models\Revenue\Hotel\DetailInvoicesHotel;
use App\Models\Revenue\Hotel\InvoicesHotel;
use App\Models\Config\ConfigCurrency;
use Illuminate\Support\Facades\File;
use Barryvdh\DomPDF\Facade\Pdf;
use Intervention\Image\Facades\Image;

class InvXeroController extends Controller
{

    private $xeroBaseUrl = 'https://api.xero.com/api.xro/2.0';
    protected $repo, $repo_detail, $service_global, $repo_jamaah, $repo_all_trans, $repo_trans_bank, $repo_over;
    use ConfigRefreshXero;
    use ApiResponse;


    public function __construct(
        InvoiceXeroLocalRepo $repo,
        InvoiceDXeroLocalRepo $repo_detail,
        TransCoaRepo $repo_all_trans,
        TransBankRepo $repo_trans_bank,
        GlobalService $service_global,
        DataJamaahXeroRepository $repo_jamaah,
        OverPayRepo $repo_over
    ) {
        $this->repo = $repo;
        $this->repo_detail = $repo_detail;
        $this->repo_all_trans = $repo_all_trans;
        $this->service_global = $service_global;
        $this->repo_jamaah = $repo_jamaah;
        $this->repo_trans_bank = $repo_trans_bank;
        $this->repo_over = $repo_over;
    }

    public function getListInvoice(Request $request)
    {


    }

    private function getRates()
    {
        // Cache selama 60 menit agar hemat kuota API dan loading cepat
        return Cache::remember('currency_rates', 60 * 60, function () {
            $apiKey = 'f759b7cefeb24896bc934f6a01c498a1';

            $response = Http::get("https://api.currencyfreaks.com/v2.0/rates/latest", [
                'apikey' => $apiKey,
                'symbols' => 'IDR,SAR,USD'
            ]);

            if ($response->successful()) {
                return $response->json()['rates'];
            }

            return null; // Handle jika error
        });
    }




    public function sarToIdr($amount)
    {
        $amountSar = $amount;
        $rates = $this->getRates();

        if (!$rates)
            return response()->json(['error' => 'Gagal ambil rate'], 500);

        $rateIDR = floatval($rates['IDR']);
        $rateSAR = floatval($rates['SAR']);
        $result = ($amountSar / $rateSAR) * $rateIDR;
        return round($result, 2);
    }

    public function getAllPaginate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'page' => 'required|integer',
            'keyword' => 'nullable|string',
            'kolom_name' => 'required|string',
            'limit' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 404);
        }
        $where = [];
        if ($request->keyword != null) {
            $data = $this->repo->searchData($where, $request->limit, $request->page, 'contact_name', strtoupper($request->keyword));
        } else {
            $data = $this->repo->getAllDataWithDefault($where, $request->limit, $request->page, 'id', 'DESC');//getDataPaginate("name",10,$request->keyword);
        }
        return $this->autoResponse($data);
    }

    private function getRateToIdr(string $currency): float
    {
        $currency = strtoupper(trim($currency));
        if (env('CONFIG_CURR_LOCAL')) {
            return MasterCurrency::where('code_curr', $currency)->value('nominal_currency');
        }

        if ($currency === 'IDR') {
            return 1.0;
        }

        $rates = $this->service_global->getRatesApi([
            'IDR',
            $currency
        ]);

        if (!isset($rates['IDR'], $rates[$currency])) {
            throw new \RuntimeException(
                "Rate {$currency} -> IDR tidak tersedia."
            );
        }

        $rateIdr = (float) $rates['IDR'];
        $rateCurrency = (float) $rates[$currency];

        if ($rateIdr <= 0 || $rateCurrency <= 0) {
            throw new \RuntimeException(
                "Rate {$currency} tidak valid."
            );
        }

        return round(
            $rateIdr / $rateCurrency,
            8
        );
    }

    public function storeParent(Request $request)//store with multy currency
    {
        $validator = Validator::make($request->all(), [
            'id' => 'nullable|integer|exists:invoices_all_from_xeros,id',
            'contact_id' => 'required|integer|exists:data_jamaah_xeros,id',
            'issue_date' => 'required|date',
            'due_date' => 'required|date',
            'reference' => 'required|string',
            'action_save' => 'required|integer|between:0,2',

            // contoh 3 huruf ISO currency
            'code_curr' => [
                'required',
                'string',
                'size:3',
                'regex:/^[A-Za-z]{3}$/'
            ],

            'item_id' => 'required|array|min:1',
            'desc' => 'required|array|min:1',
            'qty' => 'required|array|min:1',
            'unit_price' => 'required|array|min:1',
            'coa_id' => 'required|array|min:1',

            'paket_tracking_uuid' => 'nullable|array',
            'divisi_travel_tracking_uuid' => 'nullable|array',
            'id_detail' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 422);
        }

        $currency = strtoupper(trim($request->code_curr));
        $isUpdate = !empty($request->id);

        DB::beginTransaction();

        try {

            /*
            |--------------------------------------------------------------------------
            | 1. Snapshot data lama
            |--------------------------------------------------------------------------
            */

            $oldParent = $isUpdate
                ? $this->repo->whereData(['id' => $request->id])->first()
                : null;

            if ($isUpdate && !$oldParent) {
                throw new \RuntimeException('Invoice tidak ditemukan.');
            }

            $oldDetails = $isUpdate
                ? $this->repo_detail
                    ->whereData(['parent_inv_id' => $request->id])
                    ->get()
                    ->keyBy('id')
                : collect();

            /*
            |--------------------------------------------------------------------------
            | 2. Cek payment
            |--------------------------------------------------------------------------
            */

            $paymentCount = 0;

            if ($isUpdate) {
                $paymentCount = $this->repo_trans_bank
                    ->whereData([
                        'id_parent_invoice' => $request->id
                    ])
                    ->get()
                    ->count();
            }

            /*
            |--------------------------------------------------------------------------
            | 3. Currency change protection
            |--------------------------------------------------------------------------
            */

            $oldCurrency = $oldParent
                ? strtoupper((string) $oldParent->code_curr)
                : null;

            $currencyChanged = $isUpdate
                && $oldCurrency
                && $oldCurrency !== $currency;

            if ($currencyChanged && $paymentCount > 0) {
                throw new \RuntimeException(
                    "Currency invoice tidak boleh diubah dari {$oldCurrency} ke {$currency} "
                    . "karena invoice sudah memiliki {$paymentCount} transaksi payment. "
                    . "Buat invoice baru atau lakukan proses revaluation terpisah."
                );
            }

            /*
            |--------------------------------------------------------------------------
            | 4. Tentukan RATE
            |--------------------------------------------------------------------------
            |
            | Invoice existing + currency sama:
            |    JANGAN ambil rate baru.
            |
            | Currency berubah / invoice baru:
            |    Ambil rate baru.
            |
            */

            if (
                $isUpdate
                && !$currencyChanged
                && !empty($oldParent->nominal_currency)
            ) {
                $nominalCurrency = (float) $oldParent->nominal_currency;
            } else {
                $nominalCurrency = $this->getRateToIdr($currency);
            }

            /*
            |--------------------------------------------------------------------------
            | 5. Status
            |--------------------------------------------------------------------------
            */

            $status = $request->action_save == 0
                ? 'DRAFT'
                : 'AUTHORISED';

            /*
            |--------------------------------------------------------------------------
            | 6. Contact
            |--------------------------------------------------------------------------
            */

            $getContact = $this->repo_jamaah
                ->whereData(['id' => $request->contact_id])
                ->first();

            if (!$getContact) {
                throw new \RuntimeException('Contact tidak ditemukan.');
            }

            /*
            |--------------------------------------------------------------------------
            | 7. Parent invoice
            |--------------------------------------------------------------------------
            */

            $mergeData = [
                'contact_name' => $getContact->full_name,
                'uuid_contact' => 'from_local',
                'status' => $status,
                'reference' => strtolower(trim($request->reference)),
                'code_curr' => $currency,

                // snapshot rate
                'nominal_currency' => $nominalCurrency,
            ];

            if (!$isUpdate) {
                // $mergeData['invoice_number'] =
                //     $request->invoice_number != 'auto'
                //     || $request->invoice_number == null
                //     ? $request->invoice_number
                //     : $this->service_global->generateNewInvoiceNumber();

                if (empty($request->invoice_number) || $request->invoice_number === 'auto') {
                    $mergeData['invoice_number'] =
                        $this->service_global->generateNewInvoiceNumber();
                } else {
                    $mergeData['invoice_number'] =
                        $request->invoice_number;
                }

                $mergeData['invoice_uuid'] =
                    $this->service_global->generateUniqueRandomStringInvoice();

                // invoice baru selalu 0
                $mergeData['invoice_amount'] = 0;
                $mergeData['invoice_total'] = 0;
                $mergeData['less_nominal'] = 0;
            }

            $request->merge($mergeData);

            $saveP = $this->repo->CreateOrUpdate(
                $request->except([
                    'coa_id',
                    'desc',
                    'qty',
                    'unit_price',
                    'nama_paket',
                    'divisi',
                    'id_detail',
                    'action_save',
                    'invoice_nuber'
                ]),
                $request->id
            );

            /*
            |--------------------------------------------------------------------------
            | 8. Hapus detail yang dihapus user
            |--------------------------------------------------------------------------
            */

            $allDetailIds = $this->repo_detail
                ->whereData(['parent_inv_id' => $saveP->id])
                ->pluck('id')
                ->toArray();

            $providedDetailIds = $request->id_detail
                ? array_filter($request->id_detail)
                : [];

            $deletedArray = array_diff(
                $allDetailIds,
                $providedDetailIds
            );

            if (!empty($deletedArray)) {

                $deletedUuids = $this->repo_detail
                    ->wherenDataIn('id', $deletedArray)
                    ->pluck('uuid_detail_inv')
                    ->toArray();

                if (!empty($deletedUuids)) {
                    $this->repo_all_trans
                        ->wherenDataIn('uuid_detail', $deletedUuids)
                        ->delete();
                }

                $this->repo_detail
                    ->wherenDataIn('id', $deletedArray)
                    ->delete();
            }

            /*
            |--------------------------------------------------------------------------
            | 9. Save details + transaction
            |--------------------------------------------------------------------------
            */

            $detailChangeLogs = [];

            foreach ($request->coa_id as $key => $accountId) {

                $detailId = $request->id_detail[$key] ?? null;

                $qty = (float) ($request->qty[$key] ?? 0);
                $unitPrice = (float) ($request->unit_price[$key] ?? 0);

                $totalRow = round(
                    $qty * $unitPrice,
                    4
                );

                $detailData = [
                    'invoice_number' => $saveP->invoice_number,
                    'uuid_invoices' => 'from_local',
                    'uuid_item' => 'from_local',

                    'coa_id' => $accountId,
                    'desc' => $request->desc[$key] ?? null,

                    'qty' => $qty,
                    'unit_price' => $unitPrice,
                    'total_amount_each_row' => $totalRow,

                    'paket_tracking_uuid' =>
                        $request->paket_tracking_uuid[$key] ?? null,

                    'divisi_travel_tracking_uuid' =>
                        $request->divisi_travel_tracking_uuid[$key] ?? null,

                    'parent_inv_id' => $saveP->id,
                    'item_id' => $request->item_id[$key] ?? null,
                ];

                if (empty($detailId)) {
                    $detailData['uuid_detail_inv'] =
                        $this->service_global->generateUniqueString();
                }

                /*
                |--------------------------------------------------------------------------
                | Log perubahan detail
                |--------------------------------------------------------------------------
                */

                if (!empty($detailId) && $oldDetails->has($detailId)) {

                    $diffText = $this->diffDetailRow(
                        $oldDetails->get($detailId),
                        $detailData
                    );

                    if ($diffText !== '') {
                        $detailChangeLogs[] =
                            "Item '{$detailData['desc']}' diubah ({$diffText})";
                    }

                } elseif (empty($detailId)) {

                    $detailChangeLogs[] =
                        "Item '{$detailData['desc']}' ditambahkan "
                        . "(Qty: {$qty}, Harga: {$unitPrice})";
                }

                /*
                |--------------------------------------------------------------------------
                | Save detail
                |--------------------------------------------------------------------------
                */

                $saveD = $this->repo_detail->CreateOrUpdate(
                    $detailData,
                    $detailId
                );

                /*
                |--------------------------------------------------------------------------
                | Transaction COA
                |--------------------------------------------------------------------------
                */

                if ($request->action_save != 0) {

                    $nominal = (float) $saveD->total_amount_each_row;

                    $baseNominal = round(
                        $nominal * $nominalCurrency,
                        2
                    );

                    // Cari berdasarkan uuid_detail saja.
                    // Jangan pakai reference + coa sebagai identitas utama.
                    $trans = $this->repo_all_trans
                        ->whereData([
                            'uuid_detail' => $saveD->uuid_detail_inv
                        ])
                        ->first();

                    $transactionData = [
                        'date_transaction' => $request->issue_date,
                        'uuid_coa' => $accountId,
                        'reference' => $request->reference,

                        'is_speend' => false,

                        // nominal dalam currency invoice
                        'nominal' => $nominal,

                        'created_by' => $request->user_login->id,
                        'uuid_detail' => $saveD->uuid_detail_inv,

                        // MULTI CURRENCY
                        'code_curr' => $currency,
                        'nominal_currency' => $nominalCurrency,

                        // base IDR
                        'base_nominal' => $baseNominal,
                    ];

                    $this->repo_all_trans->CreateOrUpdate(
                        $transactionData,
                        $trans ? $trans->id : null
                    );
                }
            }

            /*
            |--------------------------------------------------------------------------
            | 10. Jika DRAFT -> hapus semua transaksi COA invoice
            |--------------------------------------------------------------------------
            */

            if ($request->action_save == 0) {

                $currentDetailUuids = $this->repo_detail
                    ->whereData(['parent_inv_id' => $saveP->id])
                    ->pluck('uuid_detail_inv')
                    ->filter()
                    ->toArray();

                if (!empty($currentDetailUuids)) {
                    $this->repo_all_trans
                        ->wherenDataIn(
                            'uuid_detail',
                            $currentDetailUuids
                        )
                        ->delete();
                }
            }

            /*
            |--------------------------------------------------------------------------
            | 11. Hitung total invoice
            |--------------------------------------------------------------------------
            */

            $sumD = (float) $this->repo_detail
                ->sumDataWhereDinamis(
                    ['parent_inv_id' => $saveP->id],
                    'total_amount_each_row'
                );

            /*
            |--------------------------------------------------------------------------
            | 12. Hitung payment
            |--------------------------------------------------------------------------
            |
            | Semua payment harus dikonversi ke BASE IDR dahulu.
            | Kemudian baru dibandingkan dengan invoice.
            |
            */

            $payments = $this->repo_trans_bank
                ->whereData([
                    'id_parent_invoice' => $saveP->id
                ])
                ->get();

            $totalPaidBase = 0;

            foreach ($payments as $payment) {

                if (!empty($payment->total_base_receive)) {

                    $totalPaidBase +=
                        (float) $payment->total_base_receive;

                    continue;
                }

                /*
                | Backward compatibility untuk data lama.
                */

                if (
                    !empty($payment->nominal_currency)
                    && !empty($payment->nominal_receive)
                ) {
                    $totalPaidBase +=
                        (float) $payment->nominal_receive
                        * (float) $payment->nominal_currency;
                }
            }

            /*
            |--------------------------------------------------------------------------
            | 13. Convert total payment BASE -> invoice currency
            |--------------------------------------------------------------------------
            */

            $totalPayInvoiceCurrency = 0;

            if ($nominalCurrency > 0) {
                $totalPayInvoiceCurrency = round(
                    $totalPaidBase / $nominalCurrency,
                    4
                );
            }

            /*
            |--------------------------------------------------------------------------
            | 14. Update parent totals
            |--------------------------------------------------------------------------
            */

            $newLessNominal = max(
                0,
                $sumD - $totalPayInvoiceCurrency
            );

            $invoiceStatus = $status;

            if (
                $request->action_save != 0
                && $sumD > 0
                && $totalPayInvoiceCurrency >= $sumD
            ) {
                $invoiceStatus = 'PAID';
            }

            $updateParent = [
                'invoice_total' => $sumD,

                // disimpan dalam currency invoice
                'invoice_amount' => $totalPayInvoiceCurrency,

                'less_nominal' => $newLessNominal,

                'status' => $invoiceStatus,

                'code_curr' => $currency,
                'nominal_currency' => $nominalCurrency,
            ];

            $saveP = $this->repo->CreateOrUpdate(
                $updateParent,
                $saveP->id
            );

            /*
            |--------------------------------------------------------------------------
            | 15. Overpayment
            |--------------------------------------------------------------------------
            */

            $existingOver = $this->repo_over
                ->whereData([
                    'invoice_id' => $saveP->id
                ])
                ->first();

            if ($totalPayInvoiceCurrency > $sumD) {

                $totalOverpayment = round(
                    $totalPayInvoiceCurrency - $sumD,
                    4
                );

                if ($existingOver) {

                    $this->repo_over->CreateOrUpdate([
                        'nominal_overpayment' => $totalOverpayment,
                    ], $existingOver->id);

                } else {

                    $this->repo_over->CreateOrUpdate([
                        'nominal_overpayment' => $totalOverpayment,
                        'invoice_id' => $saveP->id,
                        'trans_bank_id' => null,
                    ], null);
                }

            } elseif (
                $existingOver
                && (float) $existingOver->nominal_overpayment > 0
            ) {

                $this->repo_over->CreateOrUpdate([
                    'nominal_overpayment' => 0,
                ], $existingOver->id);
            }

            /*
            |--------------------------------------------------------------------------
            | 16. Log perubahan currency/rate
            |--------------------------------------------------------------------------
            */

            $parentChangeText = $isUpdate
                ? $this->diffParentRow(
                    $oldParent,
                    $request,
                    $saveP
                )
                : '';

            if ($currencyChanged) {
                $parentChangeText .=
                    ($parentChangeText !== '' ? '; ' : '')
                    . "Currency: {$oldCurrency} → {$currency}"
                    . "; Rate: {$nominalCurrency}";
            }

            $summaryParts = [];

            if ($parentChangeText !== '') {
                $summaryParts[] = $parentChangeText;
            }

            if (!empty($detailChangeLogs)) {
                $summaryParts[] = implode(
                    '; ',
                    $detailChangeLogs
                );
            }

            $actionLabel = $isUpdate
                ? 'mengubah'
                : 'membuat';

            $logMessage =
                $request->user_login->name
                . " {$actionLabel} transaksi invoice "
                . $saveP->contact_name
                . " [{$currency}, rate {$nominalCurrency}]";

            if (!empty($summaryParts)) {
                $logMessage .=
                    '. Detail: '
                    . implode('. ', $summaryParts);
            } else {
                $logMessage .= '.';
            }

            $this->service_global->saveLogHistory(
                $request->user_login->id,
                $logMessage,
                $request->userAgent(),
                $request->ip(),
                $saveP->id
            );

            DB::commit();

            return $this->autoResponse($saveP);

        } catch (\Throwable $th) {

            DB::rollBack();

            return $this->error(
                $th->getMessage()
                . ' at line '
                . $th->getLine(),
                422
            );
        }
    }

    public function storeParentNoCurrency(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'nullable|integer',
            'contact_id' => 'required|integer|exists:data_jamaah_xeros,id',
            'issue_date' => 'required|date',
            'due_date' => 'required|date',
            'reference' => 'required|string',
            'action_save' => 'required|integer|between:0,2',
            'code_curr' => 'required|string',
            // 'invoice_number' => 'nullable|string',

            'item_id' => 'required|array|min:1',
            'desc' => 'required|array|min:1',
            'qty' => 'required|array|min:1',
            'unit_price' => 'required|array|min:1',
            'coa_id' => 'required|array|min:1',
            'paket_tracking_uuid' => 'nullable|array',
            'divisi_travel_tracking_uuid' => 'nullable|array',
            'id_detail' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors());
        }

        if (env('CONFIG_CURR_LOCAL')) {
            $cek_nominal_currency = MasterCurrency::where('code_curr', $request->code_curr)->value('nominal_currency');
        } else {
            $cek_nominal_currency = $request->code_curr == 'SAR' ? self::sarToIdr(1) : 1;
        }

        $request->merge([
            'status' => $request->action_save == 0 ? 'DRAFT' : 'AUTHORISED', // 0->draft, 1/2->approve, harus di perbaiki
            'reference' => strtolower($request->reference),
            'nominal_currency' => $cek_nominal_currency
        ]);

        $total_pay = 0;
        $isUpdate = !empty($request->id);

        DB::beginTransaction();
        try {
            // ================== SNAPSHOT "SEBELUM" — WAJIB diambil sebelum ada mutasi apapun ==================
            $oldParent = $isUpdate ? $this->repo->whereData(['id' => $request->id])->first() : null;
            $oldDetails = $isUpdate
                ? $this->repo_detail->whereData(['parent_inv_id' => $request->id])->get()->keyBy('id')
                : collect();

            // 1. Save Parent
            $get_contact = $this->repo_jamaah->whereData(['id' => $request->contact_id])->first();

            $mergeData = [
                'contact_name' => $get_contact->full_name,
                'uuid_contact' => 'from_local'
            ];
            if (empty($request->id)) {
                $mergeData['invoice_number'] = $request->invoice_number != 'auto' || $request->invoice_number == null ? $request->invoice_number : $this->service_global->generateNewInvoiceNumber();
                $mergeData['invoice_uuid'] = $this->service_global->generateUniqueRandomStringInvoice();
            }
            $request->merge($mergeData);

            $saveP = $this->repo->CreateOrUpdate(
                $request->except(['coa_id', 'desc', 'qty', 'unit_price', 'nama_paket', 'divisi', 'id_detail', 'action_save', 'invoice_nuber']),
                $request->id
            );

            // 2. Hapus Detail yang Dibuang
            $deleted_array = [];
            if ($saveP->id) {
                $allDetailIds = $this->repo_detail->whereData(['parent_inv_id' => $saveP->id])->pluck('id')->toArray();
                $providedDetailIds = $request->id_detail ? array_filter($request->id_detail) : [];
                $deleted_array = array_diff($allDetailIds, $providedDetailIds);

                if (!empty($deleted_array)) {
                    $deletedUuids = $this->repo_detail->wherenDataIn('id', $deleted_array)->pluck('uuid_detail_inv')->toArray();
                    if (!empty($deletedUuids)) {
                        $this->repo_all_trans->wherenDataIn('uuid_detail', $deletedUuids)->delete();
                    }
                    $this->repo_detail->wherenDataIn('id', $deleted_array)->delete();
                }
            }

            // 3. Save Details (Create / Update) + catat perubahan tiap baris utk log
            $detailChangeLogs = [];

            foreach ($request->coa_id as $key => $accountId) {
                $detailId = $request->id_detail[$key] ?? null;

                $detailData = [
                    'invoice_number' => $saveP->invoice_number,
                    'uuid_invoices' => 'from_local',
                    'uuid_item' => 'from_local',
                    'coa_id' => $accountId,
                    'desc' => $request->desc[$key] ?? null,
                    'qty' => $request->qty[$key] ?? 0,
                    'unit_price' => $request->unit_price[$key] ?? 0,
                    'total_amount_each_row' => ($request->qty[$key] ?? 0) * ($request->unit_price[$key] ?? 0),
                    'paket_tracking_uuid' => $request->paket_tracking_uuid[$key] ?? null,
                    'divisi_travel_tracking_uuid' => $request->divisi_travel_tracking_uuid[$key] ?? null,
                    'parent_inv_id' => $saveP->id,
                    'item_id' => $request->item_id[$key] ?? null
                ];

                if (empty($detailId)) {
                    $detailData['uuid_detail_inv'] = $this->service_global->generateUniqueString();
                }

                // --- Bandingkan SEBELUM disimpan, terhadap snapshot lama ---
                if (!empty($detailId) && $oldDetails->has($detailId)) {
                    $diffText = $this->diffDetailRow($oldDetails->get($detailId), $detailData);
                    if ($diffText !== '') {
                        $detailChangeLogs[] = "Item '{$detailData['desc']}' diubah ({$diffText})";
                    }
                } elseif (empty($detailId)) {
                    $detailChangeLogs[] = "Item '{$detailData['desc']}' ditambahkan (Qty: {$detailData['qty']}, Harga: {$detailData['unit_price']})";
                }

                $save_d = $this->repo_detail->CreateOrUpdate($detailData, $detailId);

                // 4. Manajemen Transaksi
                if ($request->action_save != 0) {
                    $cek_create_trans = $this->repo_all_trans->whereData([
                        'reference' => $request->reference,
                        'uuid_coa' => $accountId,
                        'uuid_detail' => $save_d->uuid_detail_inv
                    ])->first();

                    if ($cek_create_trans) {
                        $cek_create_trans->is_speend = false;
                        $cek_create_trans->nominal = $save_d->total_amount_each_row ?? 0;
                        $cek_create_trans->save();
                    } else {
                        $data_trans_create = [
                            'date_transaction' => $request->issue_date,
                            'uuid_coa' => $accountId,
                            'reference' => $request->reference,
                            'is_speend' => false,
                            'nominal' => $save_d->total_amount_each_row,
                            'created_by' => $request->user_login->id,
                            'uuid_detail' => $save_d->uuid_detail_inv
                        ];
                        $this->repo_all_trans->CreateOrUpdate($data_trans_create, null);
                    }
                }
            }

            // Item yang dihapus, catat juga (nama diambil dari snapshot lama)
            foreach ($deleted_array as $delId) {
                $old = $oldDetails->get($delId);
                if ($old) {
                    $detailChangeLogs[] = "Item '{$old->desc}' dihapus";
                }
            }

            // 5. Update Total Keseluruhan Parent

            if ($isUpdate) {
                $total_pay = $this->repo_trans_bank
                    ->whereData(['id_parent_invoice' => $request->id]) // <- perbaiki di sini
                    ->get()
                    ->sum(fn($t) => $t->nominal_receive + $t->nominal_spend);
            }

            $sumD = $this->repo_detail->sumDataWhereDinamis(['parent_inv_id' => $saveP->id], 'total_amount_each_row');
            $invoiceAmount = $saveP->invoice_amount ?? 0;
            $newLessNominal = max(0, $sumD - $total_pay); //max(0, $sumD - $invoiceAmount);

            $this->repo->CreateOrUpdate(['invoice_total' => $sumD, 'less_nominal' => $newLessNominal], $saveP->id);
            $existingOver = $this->repo_over->whereData(['invoice_id' => $saveP->id])->first();
            if ($total_pay > $sumD) {//if ($invoiceAmount > $sumD) {
                // harga BERKURANG sampai di bawah yang sudah dibayar -> overpayment baru muncul / membesar
                $totalOverpayment = $total_pay - $sumD;

                if ($existingOver) {
                    $this->repo_over->CreateOrUpdate(['nominal_overpayment' => $totalOverpayment], $existingOver->id);
                } else {
                    $this->repo_over->CreateOrUpdate([
                        'nominal_overpayment' => $totalOverpayment,
                        'invoice_id' => $saveP->id,
                        'trans_bank_id' => null // tidak ada transaksi bank baru di alur ini — lihat catatan di bawah
                    ], null);
                }
            } elseif ($existingOver && $existingOver->nominal_overpayment > 0) {
                // harga BERTAMBAH sampai menutup overpayment lama -> di-nol-kan, bukan dibiarkan nyangkut
                $this->repo_over->CreateOrUpdate(['nominal_overpayment' => 0], $existingOver->id);
            }

            // ================== SUSUN PESAN LOG BERISI DETAIL PERUBAHAN ==================
            $parentChangeText = $isUpdate ? $this->diffParentRow($oldParent, $request, $saveP) : '';

            $summaryParts = [];
            if ($parentChangeText !== '') {
                $summaryParts[] = $parentChangeText;
            }
            if (!empty($detailChangeLogs)) {
                $summaryParts[] = implode('; ', $detailChangeLogs);
            }

            $actionLabel = $isUpdate ? 'currency ' . $request->code_curr . "-" . $cek_nominal_currency . ' | mengubah' : 'currency ' . $request->code_curr . "-" . $cek_nominal_currency . '| membuat';
            $logMessage = $request->user_login->name . ' ' . $actionLabel . ' transaksi invoice ' . $saveP->contact_name;
            $logMessage .= !empty($summaryParts)
                ? '. Detail: ' . implode('. ', $summaryParts)
                : ($isUpdate ? '. Tidak ada perubahan data.' : '.');

            $this->service_global->saveLogHistory(
                $request->user_login->id,
                $logMessage,
                $request->userAgent(),
                $request->ip(),
                $saveP->id
            );

            DB::commit();
            return $this->autoResponse($saveP);

        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->error($th->getMessage() . ' at line ' . $th->getLine(), 500);
        }
    }

    /**
     * Bandingkan field parent invoice (lama vs baru). Tanggal dinormalisasi ke 'Y-m-d'
     * dulu, biar '2026-01-05' vs '2026-01-05 00:00:00' tidak dianggap beda.
     */
    private function diffParentRow($oldParent, Request $request, $saveP): string
    {
        if (!$oldParent) {
            return '';
        }

        $fieldLabels = [
            'contact_name' => 'Kontak',
            'issue_date' => 'Tgl Invoice',
            'due_date' => 'Jatuh Tempo',
            'reference' => 'Referensi',
            'status' => 'Status',
            'code_curr' => 'Currency',
            'nominal_currency' => 'Rate',
        ];
        $dateFields = ['issue_date', 'due_date'];

        $newValues = [
            'contact_name' => $saveP->contact_name,
            'issue_date' => $request->issue_date,
            'due_date' => $request->due_date,
            'reference' => $request->reference,
            'status' => $request->status,
            'code_curr' => $saveP->code_curr,
            'nominal_currency' => $saveP->nominal_currency,
        ];

        $changes = [];
        foreach ($fieldLabels as $field => $label) {
            $oldVal = $oldParent->{$field} ?? null;
            $newVal = $newValues[$field] ?? null;

            if ($field === 'nominal_currency') {
                $oldVal = round((float) $oldVal, 8);
                $newVal = round((float) $newVal, 8);
            } elseif (in_array($field, $dateFields, true)) {
                $oldVal = $oldVal
                    ? Carbon::parse($oldVal)->format('Y-m-d')
                    : null;

                $newVal = $newVal
                    ? Carbon::parse($newVal)->format('Y-m-d')
                    : null;
            } else {
                $oldVal = (string) $oldVal;
                $newVal = (string) $newVal;
            }
        }

        return implode('; ', $changes);
    }

    /**
     * Bandingkan field 1 baris detail invoice (lama vs baru).
     */
    private function diffDetailRow($oldDetail, array $newDetailData): string
    {
        $fieldLabels = [
            'desc' => 'Deskripsi',
            'qty' => 'Qty',
            'unit_price' => 'Harga',
            'coa_id' => 'Akun',
            'item_id' => 'Item',
        ];

        $changes = [];
        foreach ($fieldLabels as $field => $label) {
            $oldVal = (string) ($oldDetail->{$field} ?? '');
            $newVal = (string) ($newDetailData[$field] ?? '');
            if ($oldVal !== $newVal) {
                $changes[] = "{$label}: '{$oldVal}' → '{$newVal}'";
            }
        }

        return implode(', ', $changes);
    }
    public function getImageDetail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'invoice_id' => 'required|exists:invoices_all_from_xeros,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => $validator->errors()->first()
            ], 400);
        }

        try {
            $invoiceId = $request->input('invoice_id');
            $directory = public_path('uploads/images/invoices');

            // Jika folder belum ada, berarti belum ada gambar sama sekali
            if (!File::exists($directory)) {
                return response()->json([
                    'success' => true,
                    'data' => []
                ], 200);
            }
            $pattern = $directory . '/*_' . $invoiceId . '.webp';
            $matchedFiles = glob($pattern);

            $images = [];

            if ($matchedFiles) {
                foreach ($matchedFiles as $file) {
                    $filename = basename($file);

                    $images[] = [
                        'name' => $filename,
                        'size' => filesize($file), // Dropzone butuh ukuran file (bytes)
                        'url' => url('uploads/images/invoices/' . $filename) // Full path URL
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'data' => $images
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Gagal mengambil data gambar: ' . $e->getMessage()
            ], 500);
        }
    }

    public function removeImage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file_name' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => $validator->errors()->first()
            ], 400);
        }

        try {
            $filename = $request->input('file_name');

            // Keamanan tambahan: Cegah directory traversal attack (misal nama file '../gambar.webp')
            if (preg_match('/\.\./', $filename)) {
                return response()->json(['error' => 'Nama file tidak valid.'], 400);
            }

            $filePath = public_path('uploads/images/invoices/' . $filename);

            // Cek apakah file fisik ada, lalu hapus
            if (File::exists($filePath)) {
                File::delete($filePath);

                return response()->json([
                    'success' => true,
                    'message' => 'Gambar berhasil dihapus dari server.'
                ], 200);
            }

            return response()->json(['error' => 'Gambar tidak ditemukan di server.'], 404);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Terjadi kesalahan server: ' . $e->getMessage()
            ], 500);
        }
    }

    public function uploadMultiple(Request $request)
    {
        // 1. Validasi Input Gambar (Pastikan 'file' divalidasi sebagai array)
        $validator = Validator::make($request->all(), [
            'file' => 'required|array',
            'file.*' => 'required|image|mimes:jpeg,png,jpg,webp|max:10240',
            'invoice_id' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => $validator->errors()->first()
            ], 400);
        }

        try {
            $files = $request->file('file'); // Ini sekarang adalah ARRAY dari file
            $invoiceId = $request->input('invoice_id');

            // 2. Siapkan Path
            $destinationPath = public_path('uploads/images/invoices');
            if (!file_exists($destinationPath)) {
                mkdir($destinationPath, 0755, true);
            }

            $uploadedFilesData = [];

            // 3. LOOPING UNTUK SETIAP FILE GAMBAR
            foreach ($files as $index => $file) {

                $img = Image::make($file->getRealPath());

                // Resize jika resolusi terlalu besar
                if ($img->width() > 1200) {
                    $img->resize(1200, null, function ($constraint) {
                        $constraint->aspectRatio();
                        $constraint->upsize();
                    });
                }

                $quality = 90;
                $targetSize = 90 * 1024; // 90 KB

                // Encode awal
                $encodedData = $img->encode('webp', $quality);

                // Looping kompresi untuk target 90KB
                while (strlen($encodedData) > $targetSize && $quality > 10) {
                    $quality -= 10;
                    $encodedData = $img->encode('webp', $quality);
                }

                // Resolusi darurat jika masih > 90KB
                if (strlen($encodedData) > $targetSize) {
                    $img->resize($img->width() * 0.7, null, function ($constraint) {
                        $constraint->aspectRatio();
                    });
                    $encodedData = $img->encode('webp', 40);
                }

                $invNumber = $this->repo->whereData(['id' => $invoiceId])->first();

                // Penamaan file (Gunakan uniqid agar nama tidak bentrok di dalam loop)
                $filename = $invNumber->invoice_number . '_' . uniqid() . '_' . $invoiceId . '.webp';

                // Simpan File
                file_put_contents($destinationPath . '/' . $filename, $encodedData);

                // Hitung ukuran akhir
                $finalSizeKb = round(filesize($destinationPath . '/' . $filename) / 1024, 2);

                // Simpan data file yang berhasil diproses ke array
                $uploadedFilesData[] = [
                    'file_name' => $filename,
                    'file_url' => url('uploads/images/invoices/' . $filename),
                    'final_size' => $finalSizeKb . ' KB'
                ];
            }

            // Kembalikan Response Berisi Array Data Gambar
            return response()->json([
                'success' => true,
                'message' => 'Semua gambar berhasil diupload.',
                'data' => $uploadedFilesData
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Gagal memproses gambar: ' . $e->getMessage()
            ], 500);
        }
    }

    public function detailInvoice(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:invoices_all_from_xeros,id'
        ]);
        if ($validator->fails()) {
            return $this->error($validator->errors());
        }
        // dd(222);
        $data = $this->repo->WhereDataWith([
            'getDetailById',
            'getDetailById.getCoa',
            'getDetailById.getItem',
            'getPayment',
            'getHistoryInvoice',
            'getOverPay',
            'getJamaah.listAllOverpay'
            // 'getDetailById.trackingCategoryPaket'
        ], ['id' => $request->id])->first();
        return $this->autoResponse($data);
    }


    public function storePaymentOver(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'nullable|integer',
            //'uuid_bank' => 'required|integer|exists:bank_xeros,id',
            'nominal_spend' => 'required|integer',//nominal overpay yang dibayarkan
            'reference_detail' => 'required|string',
            'date_transaction' => 'required|date',
            'parent_inv_id' => 'required|integer|exists:invoices_all_from_xeros,id',
            'overpay_id' => 'required|integer|exists:overpayments,id',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 500);
        }

        $cekData = $this->repo->whereData(['id' => $request->parent_inv_id])->first();
        $cekBankOver = $this->repo_over->whereData(['id' => $request->overpay_id])->first();

        if ($request->nominal_spend > $cekBankOver->nominal_overpayment) {
            return $this->error('nominal transfer melebihi total overpayment', 400);
        }


        $request->merge([
            'created_by' => $request->user_login->id,
            'nominal_transfer' => 0,
            'nominal_receive' => 0,
            'uuid_bank' => $cekBankOver->bank_id,
            'overpay_id' => $request->overpay_id
        ]);


        DB::beginTransaction();
        try {
            $nominal_paid_final = $cekData->invoice_amount + $request->nominal_spend;
            $final_less = max(0, $cekData->invoice_total - $nominal_paid_final);
            $param_inv_save = ['invoice_amount' => $nominal_paid_final, 'less_nominal' => $final_less];
            $invP = $this->repo->CreateOrUpdate($param_inv_save, $request->parent_inv_id);//update invoice nominal
            $request->merge([
                'id_parent_invoice' => $request->parent_inv_id
            ]);
            $saveP = $this->repo_trans_bank->CreateOrUpdate($request->all(), null);//create transaksi

            if ($invP->invoice_amount >= $invP->invoice_total) {//update status transinvoice
                $this->repo->CreateOrUpdate(['status' => 'PAID'], $request->parent_inv_id);
            }

            $actionLabel = 'membuat pembayaran invoice ' . $invP->invoice_number . " ";
            $logMessage = $request->user_login->name . ' ' . $actionLabel . ' ' . $saveP->name_contact . " sebesar " . $request->nominal_spend .
                " pada bank overpayment ";

            $this->service_global->saveLogHistory(
                $request->user_login->id,
                $logMessage,
                $request->userAgent(),
                $request->ip(),
                $invP->id,
                null
            );
            $update_repo_ = ['nominal_overpayment' => $cekBankOver->nominal_overpayment - $request->nominal_spend];
            $this->repo_over->CreateOrUpdate($update_repo_, $request->overpay_id);
            DB::commit();
            return $this->autoResponse($saveP);
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->error($th->getMessage(), 400);
        }

    }

    public function storePayment(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'id' => 'nullable|integer',
            'uuid_bank' => 'required|integer|exists:bank_xeros,id',
            'nominal_receive' => 'required|integer',
            'reference_detail' => 'required|string',
            'date_transaction' => 'required|date',
            'parent_inv_id' => 'required|integer|exists:invoices_all_from_xeros,id'
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 500);
        }

        $cekData = $this->repo->whereData(['id' => $request->parent_inv_id])->first();


        //$sisaTagihan = $cekData->less_nominal;

        // if ($request->nominal_receive > $cekData->invoice_total) { //izinkan overpayment
        //     return $this->error("Nominal melebihi total tagihan (due: {$cekData->invoice_total})", 400);
        // }

        // if ($request->nominal_receive > $sisaTagihan) { //izinkan overpayment
        //     return $this->error("Nominal melebihi sisa tagihan yang belum dibayar (sisa: {$sisaTagihan})", 400);
        // }

        $request->merge([
            'created_by' => $request->user_login->id,
            'nominal_transfer' => 0,
            'nominal_spend' => 0
        ]);

        DB::beginTransaction();
        try {
            $nominal_paid_final = $cekData->invoice_amount + $request->nominal_receive;
            // sisa tagihan = total tagihan - total dibayar, tidak boleh minus.
// kalau sudah lebih bayar (overpay), sisa tagihan otomatis 0.
            $final_less = max(0, $cekData->invoice_total - $nominal_paid_final);

            $param_inv_save = ['invoice_amount' => $nominal_paid_final, 'less_nominal' => $final_less];
            $invP = $this->repo->CreateOrUpdate($param_inv_save, $request->parent_inv_id);
            $nominalReceiveBase = ceil($request->nominal_receive * $cekData->nominal_currency);
            $request->merge([
                'id_parent_invoice' => $request->parent_inv_id,
                'total_base_receive' => $nominalReceiveBase,
                'nominal_currency' => $invP->nominal_currency
            ]);
            $saveP = $this->repo_trans_bank->CreateOrUpdate($request->all(), null);

            if ($invP->invoice_amount >= $invP->invoice_total) {
                $this->repo->CreateOrUpdate(['status' => 'PAID'], $request->parent_inv_id);
            }

            $actionLabel = 'membuat pembayaran invoice ' . $invP->invoice_number . " ";
            $logMessage = $request->user_login->name . ' ' . $actionLabel . ' ' . $saveP->name_contact . " sebesar " . $request->nominal_receive .
                " pada bank " . $saveP->name_bank;

            if ($invP->invoice_amount > $invP->invoice_total) {
                $cek_over_sbelumnya = $this->repo_over->whereData(['invoice_id' => $invP->id])->first();

                // invoice_amount sudah kumulatif -> ini otomatis TOTAL overpayment saat ini,
                // jadi ditulis ulang (bukan dijumlahkan lagi ke nominal_overpayment lama)
                $totalOverpayment = $invP->invoice_amount - $invP->invoice_total;

                if ($cek_over_sbelumnya) {
                    $this->repo_over->CreateOrUpdate(['nominal_overpayment' => $totalOverpayment], $cek_over_sbelumnya->id);
                } else {
                    $this->repo_over->CreateOrUpdate([
                        'nominal_overpayment' => $totalOverpayment,
                        'invoice_id' => $request->parent_inv_id,
                        'trans_bank_id' => $saveP->id,
                        'jamaah_contact_id' => $invP->contact_id,
                        'bank_id' => $request->uuid_bank
                    ], null);
                }
            }

            $this->service_global->saveLogHistory(
                $request->user_login->id,
                $logMessage,
                $request->userAgent(),
                $request->ip(),
                $invP->id,
                null
            );
            DB::commit();
            return $this->autoResponse($saveP);
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->error($th->getMessage(), 400);
        }


    }


    private function getHeaders()
    {
        $tokenData = $this->getValidToken();
        if (!$tokenData) {
            return response()->json(['message' => 'Token kosong/invalid. Silakan akses /xero/connect dulu.'], 401);
        }
        //dd($tokenData);
        return [
            'Authorization' => 'Bearer ' . $tokenData["access_token"],
            'Xero-Tenant-Id' => env("XERO_TENANT_ID"),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }



    public function cekPaymentAda($paymentId)
    {
        $responsePayment = Http::withHeaders($this->getHeaders())->get($this->xeroBaseUrl . '/Payments/' . $paymentId);
        $res = $responsePayment->json('Payments.0');
        if ($res != null) {
            return $res;
        } else {
            return NULL;
        }
    }







}
