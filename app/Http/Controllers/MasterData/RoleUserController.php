<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Repository\Config\MenuRepository;
use App\Http\Repository\Config\RoleMenuRepository;
use Illuminate\Http\Request;
use App\Http\Repository\MasterData\RoleUserRepository;
use Validator;
use App\Models\MasterData\BusinessLine;
use Illuminate\Validation\Rule;
use App\Traits\ApiResponse;
use DB;
class RoleUserController extends Controller
{
    use ApiResponse;

    protected $repo, $repo_roles_menu, $repo_menu;

    public function __construct(RoleUserRepository $repo, RoleMenuRepository $repo_roles_menu, MenuRepository $repo_menu)
    {
        $this->repo = $repo;
        $this->repo_roles_menu = $repo_roles_menu;
        $this->repo_menu = $repo_menu;
    }
    public function index()
    {
        $line_bis = BusinessLine::where(['is_active' => true])->get();
        return view('admin.master.role_user', ['biss' => $line_bis]);
    }
    public function getData(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'page' => 'required|integer',
            'limit' => 'required|integer',
            'kolom_name' => 'required|string',
            'keyword' => 'nullable|string',
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
            return $this->error($validator->errors(), 400);
        }

        $data = $this->repo->WhereDataWith('menus', ['id' => $request->id])->first();

        if (!$data) {
            return $this->error('Data tidak ditemukan', 404);
        }

        return $this->autoResponse([
            'id' => $data->id,
            'nama_role' => $data->nama_role,
            'is_active' => $data->is_active,
            'menus' => $data->menus
        ]);
    }


    public function saveConfig(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'roles_id' => 'required|integer|exists:master_role_users,id',
            'menus' => 'required|array|min:1',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 404);
        }
        $menus = $request->menus;

        // DB::beginTransaction();
        try {
            // $cekMenuRoles = $this->repo_roles_menu->whereData(['role_id' => $request->roles_id])->first();
            // if ($cekMenuRoles) {
            $this->repo_roles_menu->deleteWithIdDinamisMultiRow('role_id', $request->roles_id);
            // }

            $menusToSave = collect($menus);

            foreach ($menus as $menuId) {
                // Cari parent dari child ini
                $menu = $this->repo_menu->whereData(['id' => $menuId])->first();

                if ($menu && $menu->parent_id !== null && $menu->parent_id != null) {
                    // Cek apakah parent sudah ada di list yang akan di-save
                    if (!$menusToSave->contains($menu->parent_id)) {
                        $menusToSave->push($menu->parent_id);
                    }
                }
            }
            // 3. Insert semua menu (child + parent yang otomatis)
            $savedData = [];
            foreach ($menusToSave as $menuId) {
                $saved = $this->repo_roles_menu->CreateOrUpdate([
                    'role_id' => $request->roles_id,
                    'menu_id' => $menuId
                ], null);

                $savedData[] = $saved;
            }
            // DB::commit();
            return $this->success($savedData);

        } catch (\Throwable $th) {
            //DB::rollBack();
            return $this->error("gagagal " . $th->getMessage(), 500);
        }

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
            'busines_line_id' => 'required|numeric|exists:business_lines,id',
            'id' => 'nullable|integer',

        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors());
        }

        $payload = [
            'nama_role' => $request->nama_role,
            'is_active' => $request->is_active,
            'created_by' => $request->user_login->id ?? null,
            'busines_line_id' => $request->busines_line_id
        ];

        $saved = $this->repo->CreateOrUpdate($payload, $request->id);

        return $this->autoResponse($saved);
    }
}
