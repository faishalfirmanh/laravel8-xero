<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Traits\ApiResponse;
use Validator;
use Illuminate\Validation\Rule;
use App\Models\MasterData\MasterMaskapai;
use Illuminate\Support\Facades\Auth;
use App\Http\Repository\MasterData\MaskapaiRepository;


class MasterMaskapaiController extends Controller
{
    protected $repo;

    public function __construct(MaskapaiRepository $repo)
    {
        $this->repo = $repo;
    }

    public function index()
    {
        return view('admin.master.maskapai');
    }

    // LIST DATA (TABLE)
    public function getData(Request $request)
    {
        $limit   = $request->get('limit', 10);
        $search  = $request->get('search');

        $data = $this->repo->getDataPaginate(
            'nama_maskapai',
            $limit,
            $search
        );

        return response()->json([
            'status' => true,
            'data'   => $data
        ]);
    }

    // AMBIL DATA UNTUK EDIT
    public function getById(Request $request)
    {
        $data = $this->repo->findOrfail($request->id);

        return response()->json([
            'status' => true,
            'data'   => $data
        ]);
    }

    // CREATE & UPDATE
    public function save(Request $request)
    {
        $request->validate([
            'nama_maskapai' => 'required',
            'is_active'     => 'required|in:0,1'
        ]);

        $payload = [
            'nama_maskapai' => $request->nama_maskapai,
            'is_active'     => $request->is_active
        ];
        if (empty($request->id)) {
            $payload['created_by'] = Auth::id();
            $data = $this->repo->CreateOrUpdate($payload);
        }
        else {
            $data = $this->repo->CreateOrUpdate($payload, $request->id);
        }

        return response()->json([
            'status' => true,
            'data'   => $data
        ]);
    }
}
