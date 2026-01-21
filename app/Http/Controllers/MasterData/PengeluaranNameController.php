<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Repository\MasterData\PengeluaranRepository;
use App\Traits\ApiResponse;
use Validator;
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

}
