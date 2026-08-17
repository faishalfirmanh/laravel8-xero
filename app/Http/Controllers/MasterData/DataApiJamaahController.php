<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Services\GlobalService;
use DB;
use Illuminate\Http\Request;
use Validator;
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

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'nullable|integer|exists:data_jamaah_xeros,id',
            'full_name' => 'required|string|max:150',
            'phone_number' => [
                'required',
                'string',
                'max:20',
                Rule::unique('data_jamaah_xeros', 'phone_number')->ignore($request->id),
            ],
            'nik' => [
                'nullable',
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
            return $this->error('Gagal menyimpan data jamaah', 400);
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
