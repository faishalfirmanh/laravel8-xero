<?php

namespace App\Http\Controllers\Transaction\Revenue;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Http\Repository\Revenue\HotelInvoicesRepository;
use App\Http\Repository\Revenue\HotelDetailInvoicesRepository;
use Validator;
use App\Traits\ApiResponse;
use App\Services\GlobalService;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\Revenue\Hotel\DetailInvoicesHotel;
use App\Models\Revenue\Hotel\InvoicesHotel;

class RHotelApiController extends Controller
{
    //
    protected $repo, $repo_detail, $service_global;
    use ApiResponse;
    public function __construct(HotelInvoicesRepository $repo,
    HotelDetailInvoicesRepository $repo_detail,
    GlobalService $service_global)
    {
        $this->repo = $repo;
        $this->repo_detail= $repo_detail;
        $this->service_global = $service_global;
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
            $data = $this->repo->searchData($where, $request->limit, $request->page, 'nama_pemesan', strtoupper($request->keyword));
        } else {
            $data = $this->repo->getAllDataWithDefault($where, $request->limit, $request->page, 'nama_pemesan', 'ASC');//getDataPaginate("name",10,$request->keyword);
        }
        return $this->autoResponse($data);
    }

    public function store2(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'uuid_user_order' => 'required|string',
            'hotel_id'=> 'required|exists:hotels,id',
            'check_in' => 'required|date',
            'check_out' => 'required|date',
            'id'=> 'nullable|string'
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors());
        }

        $request["no_invoice_hotel"]= $this->global->generateInvoiceHotel();
        $request["nama_pemesan"]= $request->order_name;//$this->global->generateInvoiceHotel();
        $request["total_days"] = $request->total_days;
        $request["status"] = 1;
        $request["order_name"] = $request->uuid_user_order;
        $request["total_payment"] = 0;
        $saved_i = $this->repo->CreateOrUpdate($request->all(), $request->id);

        $saved_details = $this->repo_detail->CreateOrUpdate($request->all(), $request->id);

        return $this->autoResponse($saved);
    }


    public function deleteInvoiceReveueHotel(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id'=> 'required|exists:invoices_hotels,id'
        ]);
         if($validator->fails()) {
            return $this->error($validator->errors());
        }
       // dd($request->id);
        $detail =  DetailInvoicesHotel::where('invoice_id',$request->id)->delete();
        $data = InvoicesHotel::where('id',$request->id)->delete();
       if($detail){
            return response()->json(['msg'=>'success'], 200);
       }else{
            return response()->json(['msg'=>'error'], 500);
       }

    }


    public function getInvoiceReveueHotel(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id'=> 'required|exists:invoices_hotels,id'
        ]);
         if($validator->fails()) {
            return $this->error($validator->errors());
        }
        $data = $this->repo->WhereDataWith(['details'],['id'=>$request->id])->first();
        return $this->autoResponse($data);

    }

    public function store(Request $request)
{
    // 1. Validasi Input (Termasuk Array)
    $validator = Validator::make($request->all(), [
        'order_name'      => 'required', // Di HTML namenya order_name, bkn uuid_user_order
        'hotel_id'        => 'required|exists:hotels,id',
        'check_in'        => 'required|date',
        'check_out'       => 'required|date',
        'id'              => 'nullable',
        // Validasi Array Detail
        'tipe_room'       => 'required|array',
        'tipe_room.*'     => 'required',
        'qty'             => 'required|array',
        'qty.*'           => 'required|numeric|min:1',
        'price_hotel'     => 'required|array',
        'price_hotel.*'   => 'required|numeric',
    ]);

    if ($validator->fails()) {
        return $this->error($validator->errors());
    }

    DB::beginTransaction(); // Mulai transaksi manual di controller agar atomicity terjaga
    try {
        // --- A. PERSIAPAN DATA MASTER ---
        // Hitung total days jika belum ada (opsional, krn di view disabled)
        $checkIn = Carbon::parse($request->check_in);
        $checkOut = Carbon::parse($request->check_out);
        $diffDays = $checkIn->diffInDays($checkOut);

        // Hitung Total Payment dari detail yang dikirim
        $grandTotal = 0;
        if($request->has('qty') && $request->has('price_hotel')){
            foreach($request->qty as $key => $q){
                $price = $request->price_hotel[$key] ?? 0;
                $grandTotal += ($q * $price);
            }
        }

        $masterData = [
            'uuid_user_order' => $request->order_name, // Mapping dari input view
            'hotel_id'        => $request->hotel_id,
            'nama_pemesan'    => $request->order_name, // Sesuaikan logic bisnis Anda
            'check_in'        => $request->check_in,
            'check_out'       => $request->check_out,
            'total_days'      => $diffDays > 0 ? $diffDays : 1,
            'total_payment'   => $grandTotal,
            'status'          => 1,
            // 'created_by'   => auth()->id(), // Jangan lupa ini jika perlu
        ];

        // Generate No Invoice hanya jika create baru
        if (empty($request->id)) {
            $masterData['no_invoice_hotel'] = $this->service_global->generateInvoiceHotel();
        }

        // --- B. SIMPAN MASTER ---
        // Kita panggil repository, tapi hati-hati krn repo Anda punya DB::beginTransaction sendiri.
        // Sebaiknya repo hanya logic save tanpa transaction jika dipanggil dari service kompleks.
        // Tapi mari kita gunakan method CreateOrUpdate Anda.
        $savedMaster = $this->repo->CreateOrUpdate($masterData, $request->id);

        // Cek error dari return string repository Anda
        if (is_string($savedMaster) && (str_contains($savedMaster, 'error') || str_contains($savedMaster, 'no data'))) {
            throw new Exception($savedMaster);
        }

        // --- C. SIMPAN DETAIL ---
        // Hapus detail lama jika ini proses update (agar bersih)
        if (!empty($request->id)) {
            // Asumsi repo_detail punya model DetailInvoicesHotel
            $this->repo_detail->model->where('invoice_id', $savedMaster->id)->delete();
        }

        // Looping Array untuk Simpan Detail Baru
        $rooms = $request->tipe_room;
        foreach ($rooms as $index => $typeRoom) {
            $detailData = [
                'invoice_id'      => $savedMaster->id,
                'type_room'       => $typeRoom,
                'qty'             => $request->qty[$index],
                'price_each_item' => $request->price_hotel[$index],
                'total_amount'    => $request->qty[$index] * $request->price_hotel[$index],
                'desc'            => '-' // default value
            ];

            // Simpan per baris menggunakan model create biasa
            // (Tidak pakai repo->CreateOrUpdate krn itu utk single logic transaction)
            $this->repo_detail->model->create($detailData);
        }

        DB::commit(); // Commit semua perubahan (Master + Detail)
        return $this->autoResponse($savedMaster, 'Data berhasil disimpan', 200);

    } catch (Exception $e) {
        DB::rollBack();
        return $this->error('Gagal menyimpan data: ' . $e->getMessage(), 500);
    }
    }




}
