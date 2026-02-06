<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Repository\MasterData\MaskapaiRepository;
use App\Traits\ApiResponse;
use Validator;
use Illuminate\Validation\Rule;

class MasterMaskapaiController extends Controller
{
    use ApiResponse;

    protected $repo;

    public function __construct(MaskapaiRepository $repo)
    {
        $this->repo = $repo;
    }

    // =========================
    // VIEW
    // =========================
    public function index()
    {
        return view('admin.master.maskapai');
    }

    // =========================
    // GET DATA (SEARCH + PAGINATE)
    // POLA SAMA DENGAN HOTEL
    // =========================
public function getData(Request $request)
{
    $validator = Validator::make($request->all(), [
        'page'        => 'required|integer',
        'limit'       => 'required|integer',
        'keyword'     => 'nullable|string',
        'kolom_name'  => 'required|string',
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
            $request->kolom_name,
            'ASC'
        );
    }

    return $this->autoResponse($data);
}

public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nama_maskapai' => [
                'required',
                'string',
                Rule::unique('master_maskapais', 'nama_maskapai')
                    ->ignore($request->id)
            ],
            'is_active' => 'required|in:0,1',
            'id'        => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors());
        }

        $payload = [
            'nama_maskapai' => $request->nama_maskapai,
            'is_active'     => $request->is_active,
            'created_by'    => $request->user_login->id ?? null,
        ];

        $saved = $this->repo->CreateOrUpdate($payload, $request->id);

        return $this->autoResponse($saved);
    }

    // =========================
    // GET BY ID
    // =========================
    public function getById(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer|exists:master_maskapais,id'
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 404);
        }

        $data = $this->repo->find($request->id);

        if (!$data) {
            return $this->error('Data tidak ditemukan', 404);
        }

        return $this->autoResponse([
            'id'            => $data->id,
            'nama_maskapai' => $data->nama_maskapai,
            'is_active'     => $data->is_active,
        ]);
    }
    }
