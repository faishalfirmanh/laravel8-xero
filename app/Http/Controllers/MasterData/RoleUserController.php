<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Repository\MasterData\RoleUserRepository;
use Validator;
use Illuminate\Validation\Rule;
use App\Traits\ApiResponse;

class RoleUserController extends Controller
{
    use ApiResponse;

    protected $repo;

    public function __construct(RoleUserRepository $repo)
    {
        $this->repo = $repo;
    }
    public function index()
    {
        return view('admin.master.role_user');
    }
    public function getData(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'page'       => 'required|integer',
            'limit'      => 'required|integer',
            'kolom_name' => 'required|string',
            'keyword'    => 'nullable|string',
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
    public function getById(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer|exists:master_role_users,id',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 404);
        }

        $data = $this->repo->find($request->id);

        if (!$data) {
            return $this->error('Data tidak ditemukan', 404);
        }

        return $this->autoResponse([
            'id'        => $data->id,
            'nama_role' => $data->nama_role,
            'is_active' => $data->is_active,
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'nullable|integer',
            'nama_role' => [
                'required',
                'string',
                Rule::unique('master_role_users', 'nama_role')
                    ->ignore($request->id),
            ],
            'is_active' => 'required|in:0,1',
            'id' => 'nullable|integer',

        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors());
        }

        $payload = [
            'nama_role' => $request->nama_role,
            'is_active'     => $request->is_active,
            'created_by'    => $request->user_login->id ?? null,
        ];

        $saved = $this->repo->CreateOrUpdate($payload, $request->id);

        return $this->autoResponse($saved);
    }
}
