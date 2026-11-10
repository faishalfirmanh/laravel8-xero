<?php

namespace App\Http\Controllers\Transaction\Revenue;

use App\Http\Controllers\Controller;
use App\Http\Repository\MasterData\CoaRepo;
use App\Http\Repository\MasterData\Finance\InvoiceAllXeroRepo;
use App\Http\Repository\MasterData\Finance\ItemPaketAllXeroRepo;
use App\Http\Repository\Revenue\InvoiceDXeroLocalRepo;
use App\Http\Repository\Transaction\SummaryBankRepo;
use App\Http\Repository\Transaction\TransBankRepo;
use App\Http\Repository\Transaction\TransCoaRepo;
use App\Models\MasterData\BrandingThemes;
use App\Models\Transaction\TransactionAllCoa;
use Exception;
use Illuminate\Http\Request;

use App\Http\Repository\Revenue\HotelInvoicesRepository;
use App\Http\Repository\MasterData\DataJamaahXeroRepository;
use App\Http\Repository\Revenue\HotelDetailInvoicesRepository;
use Validator;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\Auth;
use App\Services\GlobalService;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use App\ConfigRefreshXero;
use App\Models\Revenue\Hotel\DetailInvoicesHotel;
use App\Models\Revenue\Hotel\InvoicesHotel;
use App\Models\Config\ConfigCurrency;
use Barryvdh\DomPDF\Facade\Pdf;


class RHotelApiController extends Controller
{
    //
    protected $repo, $repo_detail, $service_global, $repo_jamaah, $repo_inv_global, $repo_inv_global_detail,
    $repo_coa, $repo_item_master, $repo_all_trans, $repo_trans_bank, $repo_bank_summary;
    use ConfigRefreshXero;
    use ApiResponse;
    public function __construct(
        HotelInvoicesRepository $repo,
        HotelDetailInvoicesRepository $repo_detail,
        GlobalService $service_global,
        DataJamaahXeroRepository $repo_jamaah,
        InvoiceAllXeroRepo $repo_inv_global,
        InvoiceDXeroLocalRepo $repo_inv_global_detail,
        CoaRepo $repo_coa,
        ItemPaketAllXeroRepo $repo_item_master,
        TransCoaRepo $repo_all_trans,
        TransBankRepo $repo_trans_bank,
        SummaryBankRepo $repo_bank_summary
    ) {
        $this->repo = $repo;
        $this->repo_detail = $repo_detail;
        $this->service_global = $service_global;
        $this->repo_jamaah = $repo_jamaah;
        $this->repo_inv_global = $repo_inv_global;
        $this->repo_coa = $repo_coa;
        $this->repo_inv_global_detail = $repo_inv_global_detail;
        $this->repo_item_master = $repo_item_master;
        $this->repo_all_trans = $repo_all_trans;
        $this->repo_trans_bank = $repo_trans_bank;
        $this->repo_bank_summary = $repo_bank_summary;
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
            $data = $this->repo->searchData($where, $request->limit, $request->page, 'nama_pemesan', strtoupper($request->keyword), 'getInvXero');
        } else {
            $data = $this->repo->getAllDataWithDefault($where, $request->limit, $request->page, 'updated_at', 'DESC', 'getInvXero');//getDataPaginate("name",10,$request->keyword);
        }
        return $this->autoResponse($data);
    }

    public function store2(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'uuid_user_order' => 'required|string',
            'hotel_id' => 'required|exists:hotels,id',
            'check_in' => 'required|date',
            'check_out' => 'required|date',
            'id' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors());
        }

        $request["no_invoice_hotel"] = $this->global->generateInvoiceHotel();
        $request["nama_pemesan"] = $request->order_name;//$this->global->generateInvoiceHotel();
        $request["total_days"] = $request->total_days;
        $request["status"] = 1;
        $request["order_name"] = $request->uuid_user_order;
        $request["total_payment"] = 0;
        $saved_i = $this->repo->CreateOrUpdate($request->all(), $request->id);

        $saved_details = $this->repo_detail->CreateOrUpdate($request->all(), $request->id);

        return $this->autoResponse($saved_i);
    }


    public function deleteInvoiceReveueHotel(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:invoices_hotels,id'
        ]);
        if ($validator->fails()) {
            return $this->error($validator->errors());
        }

        $this->service_global->saveLogHistory(
            $request->user_login->id,
            $request->user_login->name . ' hapus invoice hotel',
            $request->userAgent(),
            $request->ip()
        );
        // dd($request->id);
        $detail = DetailInvoicesHotel::where('invoice_id', $request->id)->delete();
        $data = InvoicesHotel::where('id', $request->id)->delete();
        if ($detail) {
            return response()->json(['msg' => 'success'], 200);
        } else {
            return response()->json(['msg' => 'error'], 500);
        }

    }

    public function printInvoice(Request $request, $id)
    {
        $invoice = InvoicesHotel::with(['details', 'payments'])->find($id);
        //dd(Auth::guard('sanctum')->user());

        $data = [
            'invoice' => $invoice,
            'title' => 'Invoice #' . $invoice->no_invoice_hotel,
            'date' => date('d-m-Y'),
            // 'cetak_by'=>
        ];
        $pdf = Pdf::loadView('pdf.invoice_hotel_print', $data);
        $pdf->setPaper('A4', 'portrait');
        //return $pdf->download('Invoice-'.$invoice->no_invoice_hotel.'.pdf');
        return $pdf->stream('Invoice-' . $invoice->no_invoice_hotel . '.pdf');//tampil
    }

    public function getTotalAmount(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'date_start' => 'required|date',
            'date_end' => 'required|date',
        ]);
        if ($validator->fails()) {
            return $this->error($validator->errors());
        }
        $data_sar = $this->repo->sumWhereDateRange(
            'total_payment',
            [],
            [
                'date_start' => $request->date_start,
                'date_end' => $request->date_end,
            ],
            'date_transaction'
        );
        $data_rp = $this->repo->sumWhereDateRange(
            'total_payment_rupiah',
            [],
            [
                'date_start' => $request->date_start,
                'date_end' => $request->date_end,
            ],
            'date_transaction'
        );

        //UANG DITERIMA
        $data_rp_paid = $this->repo->sumWhereDateRange(
            'final_payment_idr',
            [],
            [
                'date_start' => $request->date_start,
                'date_end' => $request->date_end,
            ],
            'date_transaction'
        );

        $data_rp_remain = $this->repo->sumWhereDateRange(
            'less_payment_idr',
            [],
            [
                'date_start' => $request->date_start,
                'date_end' => $request->date_end,
            ],
            'date_transaction'
        );
        $final = [
            'tanggal_awal' => $request->date_start,
            'tanggal_akhir' => $request->date_end,
            'sar' => $data_sar,
            'rupiah' => $data_rp,
            'payment_idr' => $data_rp_paid,
            'remaining_idr' => $data_rp_remain
        ];
        return $this->autoResponse($final);
    }


    public function getInvoiceReveueHotel(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:invoices_hotels,id'
        ]);
        if ($validator->fails()) {
            return $this->error($validator->errors());
        }
        $data = $this->repo->WhereDataWith(['details'], ['id' => $request->id])->first();
        return $this->autoResponse($data);

    }

    public function savedRhotel(Request $request)
    {
        // 0. Normalisasi mata uang (default SAR). Jika IDR, kurs dipaksa 1 (jangan percaya input client)
        $typeCurrency = strtoupper(trim((string) $request->input('type_currency', 'SAR')));
        if ($typeCurrency === '') {
            $typeCurrency = 'SAR';
        }
        $request->merge(['type_currency' => $typeCurrency]);
        if ($typeCurrency === 'IDR') {
            $request->merge(['nominal_currency_rp' => 1]);
        }
        $isIdr = ($typeCurrency === 'IDR');

        // 1. Validasi Input (Termasuk Array)
        $validator = Validator::make($request->all(), [
            'order_name' => 'required', // Di HTML namenya order_name, bkn uuid_user_order
            'hotel_id' => 'required|exists:hotels,id',
            'check_in' => 'required|date',
            'check_out' => 'required|date|after_or_equal:check_in',
            'type_currency' => 'required|in:SAR,IDR',
            'nominal_currency_rp' => 'required|numeric|min:1',
            'id' => 'nullable',
            // Validasi Array Detail
            'tipe_room' => 'required|array',
            'tipe_room.*' => 'required',
            'qty' => 'required|array',
            'qty.*' => 'required|numeric|min:1',
            'price_hotel' => 'required|array',
            'price_hotel.*' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors());
        }

        $this->service_global->saveLogHistory(
            $request->user_login->id,
            $request->user_login->name . ' save revenue hotel',
            $request->userAgent(),
            $request->ip()
        );

        $get_contact = $this->repo_jamaah->whereData(['id' => $request->order_name])->first();
        DB::beginTransaction(); // Mulai transaksi manual di controller agar atomicity terjaga
        try {
            // --- A. PERSIAPAN DATA MASTER ---
            $checkIn = Carbon::parse($request->check_in);
            $checkOut = Carbon::parse($request->check_out);
            $diffDays = $checkIn->diffInDays($checkOut);
            $totalDays = $diffDays > 0 ? $diffDays : 1;

            // Kurs: SAR -> dari input, IDR -> selalu 1
            $rate = $isIdr ? 1 : (float) $request->nominal_currency_rp;

            // Hitung Total Payment dari detail yang dikirim
            // SAR: grandTotal dalam SAR | IDR: grandTotal dalam Rupiah
            $grandTotal = 0;
            foreach ($request->qty as $key => $q) {
                $grandTotal += ($q * ($request->price_hotel[$key] ?? 0) * $totalDays);
            }

            $config_curency = ConfigCurrency::first();
            $final_rupiah_amount = $grandTotal * $rate; // IDR: sama dengan $grandTotal

            $request['nama_pemesan'] = $get_contact->full_name;
            $request['total_days'] = $totalDays;
            $request['total_payment'] = $grandTotal;                 // SAR (atau Rupiah jika type_currency IDR)
            $request['total_payment_rupiah'] = $final_rupiah_amount; // selalu Rupiah
            $request['uuid_user_order'] = $request->order_name;
            $request['less_payment_sar'] = $grandTotal;
            $request['less_payment_idr'] = $final_rupiah_amount;
            $request['contact_id_x'] = $request->order_name;
            $request['created_by'] = $request->user_login->id;
            $request['status'] = 1;
            $request['type_currency'] = $typeCurrency;
            $request['nominal_currency_rp'] = $rate;
            if (empty($request->id)) {
                $request['no_invoice_hotel'] = $this->service_global->generateInvoiceHotel();
            }

            $cek_phone = $get_contact->phone_number ?? '00';
            $param_update_jamaah = ['uuid_contact' => $request->order_name];
            $param_create_jmaah = ['full_name' => $get_contact->full_name, 'phone_number' => $cek_phone, 'is_mitra_trevel' => true];
            $this->repo_jamaah->firstOrCreata($param_update_jamaah, $param_create_jmaah);

            // No invoice hanya dibuat saat create baru, saat update jangan ditimpa
            $payload = $request->all();
            if (!empty($request->id)) {
                unset($payload['no_invoice_hotel']);
            }

            $savedMaster = $this->repo->CreateOrUpdate($payload, $request->id);

            // Cek error dari return string repository Anda
            if (is_string($savedMaster) && (strpos($savedMaster, 'error') !== false || strpos($savedMaster, 'no data') !== false)) {
                throw new Exception($savedMaster);
            }

            // --- C. SIMPAN DETAIL ---
            // Hapus detail lama jika ini proses update (agar bersih)
            if (!empty($request->id)) {
                $this->repo_detail->model->where('invoice_id', $savedMaster->id)->delete();
            }

            // Keterangan per baris detail
            if ($isIdr) {
                $descDetail = 'Harga input IDR, kurs 1 = Rp 1 (type currency IDR)';
            } else {
                $descDetail = 'kurs 1 SAR ke Rupiah ' .
                    $config_curency->nominal_rupiah_1_riyal
                    . " update terakhir " . $config_curency->updated_at;
            }

            // Looping Array untuk Simpan Detail Baru
            $rooms = $request->tipe_room;
            foreach ($rooms as $index => $typeRoom) {
                $detailData = [
                    'invoice_id' => $savedMaster->id,
                    'type_room' => $typeRoom,
                    'qty' => $request->qty[$index],
                    'price_each_item' => $request->price_hotel[$index],   // SAR, atau Rupiah jika IDR
                    'total_amount' => $request->qty[$index] * $request->price_hotel[$index],
                    'desc' => $descDetail,
                ];

                $this->repo_detail->model->create($detailData);
            }

            $modelDetail = $this->repo_detail->model->where('invoice_id', $savedMaster->id)->get();
            $qStringDetail = DetailInvoicesHotel::buildRoomString($modelDetail, $totalDays);

            $globalInvoice = $this->syncGlobalInvoice(
                $request,
                $savedMaster,
                $get_contact,
                $qStringDetail,
                (float) $grandTotal
            );

            DB::commit();
            return $this->autoResponse($savedMaster, 'Data berhasil disimpan', 200);

        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->error('Gagal menyimpan data: ' . $e->getMessage(), 500);
        }
    }

    private function syncGlobalInvoice(Request $request, $hotelInv, $contact, string $roomString, float $grandTotal)
    {
        $user = $request->user_login;

        // Mata uang invoice (default SAR). IDR => kurs 1
        $typeCurrency = strtoupper((string) ($request->input('type_currency') ?: ($hotelInv->type_currency ?: 'SAR')));
        $isIdr = ($typeCurrency === 'IDR');
        $codeCurr = $isIdr ? 'IDR' : 'SAR';

        // 1. Item master hotel001: ambil, kalau tidak ada buat
        $coaa = $this->repo_coa->whereData(['code' => '4112'])->first();
        $item = $this->repo_item_master->whereData(['code' => 'hotel001'])->first();
        if (!$item) {
            $item = $this->repo_item_master->CreateOrUpdate([
                'uuid_proudct_and_service' => 'from_web',   // typo "proudct" memang nama kolomnya
                'code' => 'hotel001',
                'nama_paket' => 'item hotel only',
                'sales_AccountCode' => '4112',
                'jenis_item' => 1,
                'price_sales' => 0,
                'desc' => 'item hotel dari invoice hotel',
                'account_id_salles' => $coaa->id,
                'desc_salles' => 'item hotel dari invoice hotel',
                'tax_rate_salles' => 0,
                'tax_rate_purchase' => 0,
            ], null);
        }

        if (!is_object($item)) {
            throw new \RuntimeException('Gagal membuat item hotel001: ' . json_encode($item));
        }


        // 2. COA 4112
        $coa = $this->repo_coa->whereData(['code' => '4112'])->first();
        if (!$coa) {
            throw new \RuntimeException('COA dengan code 4112 tidak ditemukan.');
        }

        // 3. Invoice global existing (kalau edit)
        $global = $this->repo_inv_global
            ->whereData(['invoice_number' => $hotelInv->no_invoice_hotel])
            ->first();

        // Pengaman: jangan ganti SAR <-> IDR kalau sudah ada pembayaran (nominal bayar tersimpan dalam mata uang lama)
        if (
            $global
            && !empty($global->code_curr)
            && strtoupper($global->code_curr) !== $codeCurr
            && (float) $global->invoice_amount > 0
        ) {
            throw new \RuntimeException('Mata uang invoice tidak bisa diubah karena sudah ada pembayaran. Hapus pembayaran terlebih dahulu.');
        }

        // branding
        $branding = BrandingThemes::find(BrandingThemes::defaultId()) ?: BrandingThemes::first();
        if (!$branding) {
            throw new \RuntimeException('Branding theme default tidak ditemukan.');
        }
        $issueDate = $request->date_transaction ?: Carbon::now()->toDateString();

        // Kurs: SAR -> dari input (tidak dikunci saat edit) | IDR -> selalu 1
        $rate = $isIdr ? 1.0 : (float) $request->nominal_currency_rp;

        $parent = [
            'contact_id' => $contact->id,
            'contact_name' => $contact->full_name,
            'uuid_contact' => 'from_local',
            'issue_date' => $issueDate,
            'due_date' => $request->check_in,
            'reference' => strtolower(trim($hotelInv->no_invoice_hotel)),
            'code_curr' => $codeCurr,
            'nominal_currency' => $rate,
            'branding_theme_id' => $branding->id,
            'travel_id' => $branding->travel_id,
            'created_by' => $user->id,
            'created_by_name' => 'web-' . $user->name,
            'invoice_total' => $grandTotal,
        ];

        if ($global) {
            $paid = (float) $global->invoice_amount; // sudah dibayar, dalam mata uang invoice
            $parent['less_nominal'] = max(0, $grandTotal - $paid);
            $parent['status'] = ($paid > 0 && $paid >= $grandTotal) ? 'PAID' : 'AUTHORISED';
        } else {
            $parent['invoice_number'] = $hotelInv->no_invoice_hotel;
            $parent['invoice_uuid'] = $this->service_global->generateUniqueRandomStringInvoice();
            $parent['invoice_amount'] = 0;
            $parent['less_nominal'] = $grandTotal;
            $parent['status'] = 'AUTHORISED';
        }

        $globalInv = $this->repo_inv_global->CreateOrUpdate($parent, $global ? $global->id : null);
        // setelah CreateOrUpdate invoice global
        if (!is_object($globalInv)) {
            throw new \RuntimeException('Gagal simpan invoice global: ' . (is_string($globalInv) ? $globalInv : 'repo tidak mengembalikan model'));
        }


        // 4. Detail: 1 baris, qty 1, desc = string room
        $detail = $this->repo_inv_global_detail->whereData(['parent_inv_id' => $globalInv->id])->first();

        $detailData = [
            'invoice_number' => $globalInv->invoice_number,
            'uuid_invoices' => 'from_local',
            'uuid_item' => 'from_local',
            'coa_id' => $coa->id,
            'desc' => $roomString,
            'qty' => 1,
            'unit_price' => $grandTotal,
            'total_amount_each_row' => $grandTotal,
            'parent_inv_id' => $globalInv->id,
            'item_id' => $item->id,
            'sort_order' => 0,
        ];
        if (!$detail) {
            $detailData['uuid_detail_inv'] = $this->service_global->generateUniqueString();
        }
        $saveD = $this->repo_inv_global_detail->CreateOrUpdate($detailData, $detail ? $detail->id : null);
        // setelah CreateOrUpdate detail
        if (!is_object($saveD)) {
            throw new \RuntimeException('Gagal simpan detail invoice global: ' . (is_string($saveD) ? $saveD : 'repo tidak mengembalikan model'));
        }

        // 5. Jurnal COA (invoice AUTHORISED)
        $trans = $this->repo_all_trans->whereData(['uuid_detail' => $saveD->uuid_detail_inv])->first();
        $this->repo_all_trans->CreateOrUpdate([
            'date_transaction' => $issueDate,
            'uuid_coa' => $coa->id,
            'reference' => $globalInv->reference,
            'is_speend' => false,
            'nominal' => $grandTotal,
            'created_by' => $user->id,
            'uuid_detail' => $saveD->uuid_detail_inv,
            'code_curr' => $codeCurr,
            'nominal_currency' => $rate,
            'base_nominal' => round($grandTotal * $rate, 2), // IDR: sama dengan nominal
        ], $trans ? $trans->id : null);

        return $globalInv;
    }


}
