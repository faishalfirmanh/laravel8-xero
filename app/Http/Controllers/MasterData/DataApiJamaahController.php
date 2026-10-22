<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Services\GlobalService;
use DB;
use Illuminate\Http\Request;
use Validator;
use App\Models\DataJamaah;
use App\Traits\ApiResponse;
use Illuminate\Validation\Rule;
use App\Http\Repository\MasterData\DataJamaahXeroRepository;
class DataApiJamaahController extends Controller//from xero when create transaction invoice hotel
{
    //

    protected $repo, $global_service;
    use ApiResponse;
    public function __construct(DataJamaahXeroRepository $repo, GlobalService $global_service)
    {
        $this->repo = $repo;
        $this->global_service = $global_service;
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
            $data = $this->repo->searchData($where, $request->limit, $request->page, 'full_name', strtoupper($request->keyword));
        } else {
            $data = $this->repo->getAllDataWithDefault($where, $request->limit, $request->page, 'full_name', 'ASC');//getDataPaginate("name",10,$request->keyword);
        }
        return $this->autoResponse($data);
    }


    public function storeGlobal(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'full_name' => 'required|string|max:150',
            'phone_number' => [
                'required',
                'string',
                'max:20',
            ],
            'nik' => [
                'nullable',
            ],
            'detail_address' => 'nullable|string|max:500',
            'key' => 'required|string|in:namiroh123',
        ]);


        $data = $validator->validated();
        $data['uuid_contact'] = $this->global_service->generateUniqueString();
        DB::beginTransaction();
        try {
            if ($request->nik) {
                $caridata = $this->repo->whereData(['nik' => $request->nik])->first();
                if ($caridata == NULL)
                    $saved = $this->repo->CreateOrUpdate($data, null);
            }
            DB::commit();
            return $this->autoResponse($saved);
        } catch (\Throwable $th) {
            DB::rollBack();
            \Log::error('store jamaah error: ' . $th->getMessage());
            return $this->error('Gagal menyimpan data jamaah', 400);
        }
    }

    public function storeAlhid(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_jamaah' => 'nullable|integer|exists:mysql_2.data_jamaah,id_jamaah',
            'full_name' => 'required|string|max:100',
            'nik' => 'required|string|max:255',
            'phone_number' => 'nullable|string',
            'detail_address' => 'nullable|string'
        ]);

        //dd($request->all());
        if ($validator->fails()) {
            return $this->error($validator->errors(), 400);
        }
        $data = $validator->validated();

        DB::connection('mysql_2')->beginTransaction();

        try {
            $id = isset($data['id_jamaah'])
                ? $data['id_jamaah']
                : null;

            unset($data['id_jamaah']);

            if ($id) {
                // Update data yang sudah ada.
                $jamaah = DataJamaah::find($id);

                if (!$jamaah) {
                    DB::connection('mysql_2')->rollBack();
                    return $this->error('Data jamaah tidak ditemukan', 404);
                }

                $jamaah->nama_jamaah = $data['full_name'];
                $jamaah->no_ktp = $data['nik'];
                $jamaah->alamat_jamaah = $data['detail_address'];
                $jamaah->no_telp = $data['phone_number'];
                $jamaah->save();
            } else {
                // Default untuk kolom NOT NULL saat membuat data baru.
                $data = array_merge([
                    'agen' => 0,
                    'tempat_lahir' => 'Create by web',
                    'estimasi_berangkat' => date('Y-m-d'),
                    'imigrasi' => 0,
                    'leader' => 1,
                    'nama_jamaah' => $data['full_name'],
                    'no_ktp' => $data['nik'],
                    'id_status' => 2,
                    'nama_tambahan' => '',
                    'alamat_jamaah' => $data['detail_address'],
                    'nama_di_vaksin' => '',
                    'jenis_vaksin' => '[Belum Vaksin]',
                    'no_tlp' => $data['phone_number'],
                    'is_updated_to_xero' => 0,
                    'is_updated' => 0,
                    'status_generate' => 0,
                ], $data);
                //dd($data);
                $jamaah = DataJamaah::create($data);
            }

            DB::connection('mysql_2')->commit();
            return $this->autoResponse($jamaah);

        } catch (\Throwable $th) {
            DB::connection('mysql_2')->rollBack();

            \Log::error('store data jamaah error: ' . $th->getMessage());

            return $this->error(
                'Gagal menyimpan data jamaah: ' . $th->getMessage(),
                400
            );
        }
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'nullable|integer|exists:data_jamaah_xeros,id',
            'full_name' => 'required|string|max:150',
            'phone_number' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('data_jamaah_xeros', 'phone_number')->ignore($request->id),
            ],
            'nik' => [
                'required',
                // 'digits:16',
                Rule::unique('data_jamaah_xeros', 'nik')->ignore($request->id),
            ],
            'detail_address' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 400);
        }

        $data = $validator->validated();
        unset($data['id']); // id dipakai sebagai kunci CreateOrUpdate, bukan kolom yang diisi
        if ($request->id == NULL)
            $data['uuid_contact'] = $this->global_service->generateUniqueString();
        DB::beginTransaction();
        try {
            $saved = $this->repo->CreateOrUpdate($data, $request->id);
            DB::commit();
            return $this->autoResponse($saved);
        } catch (\Throwable $th) {
            DB::rollBack();
            \Log::error('store jamaah error: ' . $th->getMessage());
            return $this->error('Gagal menyimpan data jamaah ' . $th->getMessage(), 400);
        }
    }

    public function getById(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:data_jamaah_xeros,id'
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors());
        }

        $data = $this->repo->WhereDataWith(['transHotel'], ['id' => $request->id])->first();
        return $this->autoResponse($data);
    }

}
