<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Repository\MasterData\PengeluaranRepository;
use App\Traits\ApiResponse;
use Validator;
use Illuminate\Validation\Rule;
use App\Models\MasterData\MasterPengeluaranPaket;

class PengeluaranNameController extends Controller
{
    use ApiResponse;

    protected $repo;

    public function __construct(PengeluaranRepository $repo)
    {
        $this->repo = $repo;
    }

    // =========================
    // GET DATA (SEARCH + PAGINATE)
    // POLA SAMA DENGAN MASKAPAI
    // =========================
public function getData(Request $request)
{
    $validator = Validator::make($request->all(), [
        'page'       => 'required|integer',
        'limit'      => 'required|integer',
        'kolom_name' => 'required|string',
        'keyword'    => 'nullable|string'
    ]);

    if ($validator->fails()) {
        return $this->error($validator->errors(), 404);
    }

    $where = [];

    if ($request->keyword) {
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

    // =========================
    // GET BY ID
    // =========================
    public function getById(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer|exists:master_pengeluaran_pakets,id'
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 404);
        }

        $data = $this->repo->find($request->id);

        if (!$data) {
            return $this->error('Data tidak ditemukan', 404);
        }

        return $this->autoResponse([
            'id'               => $data->id,
            'nama_pengeluaran' => $data->nama_pengeluaran,
            'is_active'        => $data->is_active,
        ]);
    }

    // =========================
    // CREATE / UPDATE
    // =========================
public function store(Request $request)
{
    $validator = Validator::make($request->all(), [
        'id' => 'nullable|integer',
        'nama_pengeluaran' => [
            'required',
            'string',
            Rule::unique('master_pengeluaran_pakets', 'nama_pengeluaran')
                ->ignore($request->id),
        ],
        'is_active' => 'required|in:0,1',
    ]);

    if ($validator->fails()) {
        return $this->error($validator->errors());
    }

    $payload = [
        'nama_pengeluaran' => strtoupper($request->nama_pengeluaran),
        'is_active'        => $request->is_active,
        'created_by'       => $request->user_login->id ?? null,
    ];

    $data = $this->repo->CreateOrUpdate($payload, $request->id);

    return $this->autoResponse($data);
}
    // =========================
    // SELECT2 / DROPDOWN (LOCAL)
    // TETAP DIPAKAI – TIDAK DIUBAH BANYAK
    // =========================
    public function getAllNamePengeluaranLocal(Request $request)
    {
        $page   = max((int) $request->get('page', 1), 1);
        $limit  = 10;
        $offset = ($page - 1) * $limit;

        $keyword = strtoupper(trim($request->get('keyword', '')));

        $query = MasterPengeluaranPaket::query()
            ->where('is_active', 1)
            ->select('id', 'nama_pengeluaran');

        if ($keyword !== '') {
            $query->whereRaw(
                'UPPER(nama_pengeluaran) LIKE ?',
                ['%' . $keyword . '%']
            );
        }

        $total = (clone $query)->count();

        $data = $query
            ->orderBy('nama_pengeluaran')
            ->offset($offset)
            ->limit($limit)
            ->get();

        return response()->json([
            'status'  => 'success',
            'results' => $data,
            'pagination' => [
            'more' => ($offset + $limit) < $total
            ]
        ], 200);
    }
    
}
