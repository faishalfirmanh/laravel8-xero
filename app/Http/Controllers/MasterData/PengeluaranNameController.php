<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Repository\MasterData\PengeluaranRepository;
use App\Traits\ApiResponse;
use Validator;
use App\Models\MasterData\MasterPengeluaranPaket;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
class PengeluaranNameController extends Controller
{
    //
    protected $repo;
    use ApiResponse;

    public function __construct(PengeluaranRepository $repo)
    {
        $this->repo = $repo;
    }

    public function getData(Request $request)
    {
        $validator = Validator::make($request->all(),[
            'limit' => 'required|numeric',
            'page'=>'required|numeric',
            'search' => 'nullable|string'
        ]);

        if ($validator->fails()) {
           return $this->error($validator->errors());
        }
        $limit   = $request->limit;
        $keyword = $request->search;
        $kolom_pencarian = 'nama_pengeluaran';
        $getData = $this->repo->getDataPaginate($kolom_pencarian, $limit, $keyword);

        return $this->autoResponse($getData);

        // return response()->json([
        //     'status'  => 'success',
        //     'data'    => $getData,
        //     'message' => 'Data fetched successfully' ]);
        //   return response()->json(['status' => 'success','data'=>$getData ,'message' => 'Failed to delete'], 200);
    }

    public function getById(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:master_pengeluaran_pakets,id'
        ]);
        if ($validator->fails()) {
            return $this->error($validator->errors());
        }
        $data = $this->repo->whereData( ['id' => $request->id])->first();
        return $this->autoResponse($data);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'nullable|integer',
            'nama_pengeluaran' => [
                'required',
                'string',
                Rule::unique('master_pengeluaran_pakets', 'nama_pengeluaran')->ignore($request->id)
            ],
            'is_active' => 'required|integer|between:0,3',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors());
        }

        $saved = $this->repo->CreateOrUpdate($request->all(), $request->id);
        return $this->autoResponse($saved);
    }

     public function getAllNamePengeluaranLocal(Request $request)
    {
        $page = max((int) $request->get('page', 1), 1);
        $limit = 10; // Naikkan sedikit limitnya biar UX lebih enak (misal 10 atau 20)
        $offset = ($page - 1) * $limit;

        $keyword = strtoupper(trim($request->get('keyword', '')));
        $query = MasterPengeluaranPaket::query()
            ->where('is_active',1)
            ->select([
                'id',
                'nama_pengeluaran',
            ]);

        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->whereRaw('UPPER(nama_pengeluaran) LIKE ?', ['%' . $keyword . '%']);
            });
        }

        $totalQuery = clone $query; // Clone untuk hitung total sebelum limit
        $total = $totalQuery->count();

        $data = $query->orderBy('created_at', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();

        return response()->json([
            'status' => 'success',
            'results' => $data,
            'pagination' => [
                'more' => ($offset + $limit) < $total
            ]
        ], 200);
    }

}
