<?php

namespace App\Http\Controllers\Transaction\Revenue;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Http\Repository\Revenue\HotelInvoicesRepository;
use App\Http\Repository\MasterData\DataJamaahXeroRepository;
use App\Http\Repository\Revenue\HotelDetailInvoicesRepository;
use Validator;
use App\Traits\ApiResponse;
use App\Services\GlobalService;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use App\ConfigRefreshXero;
use App\Models\Revenue\Hotel\DetailInvoicesHotel;
use App\Models\Revenue\Hotel\InvoicesHotel;
use App\Models\Config\ConfigCurrency;
class RHotelApiController extends Controller
{
    //
    protected $repo, $repo_detail, $service_global, $repo_jamaah;
    use ConfigRefreshXero;
    use ApiResponse;
    public function __construct(
        HotelInvoicesRepository $repo,
        HotelDetailInvoicesRepository $repo_detail,
        GlobalService $service_global,
        DataJamaahXeroRepository $repo_jamaah
    ) {
        $this->repo = $repo;
        $this->repo_detail = $repo_detail;
        $this->service_global = $service_global;
        $this->repo_jamaah = $repo_jamaah;
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
        // dd($request->id);
        $detail = DetailInvoicesHotel::where('invoice_id', $request->id)->delete();
        $data = InvoicesHotel::where('id', $request->id)->delete();
        if ($detail) {
            return response()->json(['msg' => 'success'], 200);
        } else {
            return response()->json(['msg' => 'error'], 500);
        }

    }

    public function getTotalAmount(Request $request)
    {
         $validator = Validator::make($request->all(), [
            'date_start' => 'required|date',
            'date_end'=>  'required|date',
        ]);
        if ($validator->fails()) {
            return $this->error($validator->errors());
        }
        $data_sar = $this->repo->sumWhereDateRange(
            'total_payment',
            [],
            [
                'date_start'=>$request->date_start,
                'date_end'=>$request->date_end,
            ],
            'date_transaction');
        $data_rp = $this->repo->sumWhereDateRange(
            'total_payment_rupiah',
            [],
            [
                'date_start'=>$request->date_start,
                'date_end'=>$request->date_end,
            ],
            'date_transaction');
        $final = [
            'tanggal_awal'=>$request->date_start,
            'tanggal_akhir'=>$request->date_end,
            'sar'=>$data_sar,
            'rupiah'=> $data_rp
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

    public function store(Request $request)
    {
        // 1. Validasi Input (Termasuk Array)
        $validator = Validator::make($request->all(), [
            'order_name' => 'required', // Di HTML namenya order_name, bkn uuid_user_order
            'hotel_id' => 'required|exists:hotels,id',
            'check_in' => 'required|date',
            'check_out' => 'required|date',
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

        $tokenData = $this->getValidToken();

        if (!$tokenData) {
            return response()->json([
                'message' => 'Token kosong / invalid'
            ], 401);
        }
        // Ambil tenant ID secara dinamis (recommended)
        $tenantId = $this->getTenantId($tokenData['access_token']);
        if (!$tenantId) {
            return response()->json([
                'message' => 'Tenant ID tidak ditemukan'
            ], 400);
        }
        $resContact = Http::withHeaders([
            'Authorization' => 'Bearer ' . $tokenData['access_token'],
            'Xero-Tenant-Id' => $tenantId,
            'Accept' => 'application/json'
        ])->get('https://api.xero.com/api.xro/2.0/Contacts', [
                    'where' => 'ContactID==Guid("' . $request->order_name . '")'
                ]);
        $contact = $resContact['Contacts'][0] ?? null;
        //dd($contact);
        $first = isset($contact["FirstName"]) ? $contact["FirstName"] : "_";
        $last = isset($contact["LastName"]) ? $contact["LastName"] : "_";
        $full_name = $contact["Name"] . "_" . $first . "_" . $last;
        $cek_phone = isset($contact["Phones"][3]["PhoneNumber"]) ? $contact["Phones"][3]["PhoneNumber"] : 0;//type mobile
        //dd($cek_phone);

        //DB::beginTransaction(); // Mulai transaksi manual di controller agar atomicity terjaga
        try {
            // --- A. PERSIAPAN DATA MASTER ---
            // Hitung total days jika belum ada (opsional, krn di view disabled)
            $checkIn = Carbon::parse($request->check_in);
            $checkOut = Carbon::parse($request->check_out);
            $diffDays = $checkIn->diffInDays($checkOut);

            // Hitung Total Payment dari detail yang dikirim
            $grandTotal = 0;
            if ($request->has('qty') && $request->has('price_hotel')) {
                foreach ($request->qty as $key => $q) {
                    $price = $request->price_hotel[$key] ?? 0;
                    $grandTotal += ($q * $price);
                }
            }
            $config_curency = ConfigCurrency::first();
            $final_rupiah_amount = $grandTotal * $config_curency->nominal_rupiah_1_riyal;
           // $request->request->add(['nama_pemesan'=> json_encode($request->list_product_id)]);
            $request['nama_pemesan'] = $full_name;
            $request['total_days'] =  $diffDays > 0 ? $diffDays : 1;
            $request['total_payment'] =  $grandTotal;
            $request['total_payment_rupiah'] =  $final_rupiah_amount;
            $request['uuid_user_order'] =  $request->order_name;
             $request['status'] = 1;
            $request['no_invoice_hotel'] = $this->service_global->generateInvoiceHotel();//  $final_rupiah_amount;



            //disabled
            $masterData = [
                // 'uuid_user_order' => $request->order_name, // Mapping dari input view
                // 'hotel_id' => $request->hotel_id,
                // 'nama_pemesan' => $full_name, // Sesuaikan logic bisnis Anda
                // 'check_in' => $request->check_in,
                // 'check_out' => $request->check_out,
                // 'total_days' => $diffDays > 0 ? $diffDays : 1,
                // 'total_payment' => $grandTotal,
                // 'total_payment_rupiah'=>$final_rupiah_amount,
                // 'date_transaction'=>$request->date_transaction,
                // 'status' => 1,
                // 'created_by'   => auth()->id(), // Jangan lupa ini jika perlu
            ];

            $param_update_jamaah = ['uuid_contact'=> $request->order_name];
            $param_create_jmaah = ['full_name'=>$full_name,'phone_number'=>$cek_phone,'is_mitra_trevel'=>true];
            $this->repo_jamaah->firstOrCreata($param_update_jamaah,$param_create_jmaah);

            // Generate No Invoice hanya jika create baru
            if (empty($request->id)) {
                $masterData['no_invoice_hotel'] = $this->service_global->generateInvoiceHotel();
            }

            $savedMaster = $this->repo->CreateOrUpdate($request->all(), $request->id);

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
                    'invoice_id' => $savedMaster->id,
                    'type_room' => $typeRoom,
                    'qty' => $request->qty[$index],
                    'price_each_item' => $request->price_hotel[$index],
                    'total_amount' => $request->qty[$index] * $request->price_hotel[$index],
                    'desc' => 'kurs 1 SAR ke Rupiah '.
                    $config_curency->nominal_rupiah_1_riyal
                    ." update terakhir ".$config_curency->updated_at
                ];

                // Simpan per baris menggunakan model create biasa
                // (Tidak pakai repo->CreateOrUpdate krn itu utk single logic transaction)
                $this->repo_detail->model->create($detailData);
            }

            // $total_nomial_rupiah = ['total_payment_rupiah'=> $final_rupiah_amount ];
            // $this->repo->CreateOrUpdate($total_nomial_rupiah, $savedMaster->id);
            //dd();
            //DB::commit(); // Commit semua perubahan (Master + Detail)
            return $this->autoResponse($savedMaster, 'Data berhasil disimpan', 200);

        } catch (Exception $e) {
           // DB::rollBack();
            return $this->error('Gagal menyimpan data: ' . $e->getMessage(), 500);
        }
    }




}
