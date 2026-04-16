<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Repository\Config\RelasiRoleUserRepository;
use App\Http\Repository\Config\RoleMenuRepository;
use App\Http\Repository\MasterData\RoleUserRepository;
use Illuminate\Http\Request;

use App\Http\Repository\MasterData\CoaRepo;
use App\Models\Config\TravelUser;
use App\Http\Repository\Transaction\SpendMoneyRepo;
use App\Http\Repository\Config\TravelUserRepository;
use App\Http\Repository\UserRepository;
use App\Http\Repository\Revenue\HotelDetailInvoicesRepository;
use Validator;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\Auth;
use App\Services\GlobalService;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\PaymentParams;
use Illuminate\Support\Facades\Http;
use App\ConfigRefreshXero;
use App\Models\Revenue\Hotel\DetailInvoicesHotel;
use App\Models\Revenue\Hotel\InvoicesHotel;
use App\Models\Config\ConfigCurrency;

use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Validation\Rule;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use App\Models\Config\RoleUsers;
use App\Models\Config\RoleMenus;
use App\Models\MasterData\Menu;
class UserController extends Controller
{

    use ApiResponse;
    protected $repo,
    $repo_role_user,
    $repo_menu_roles,
    $repo_menu,
    $repo_master_roles,
    $repo_travel_user;

    public function __construct(
        UserRepository $repo,
        TravelUserRepository $repo_travel_user,
        RoleUserRepository $repo_master_roles,
        RelasiRoleUserRepository $repo_role_user,
        RoleMenuRepository $repo_menu_roles
    ) {

        $this->repo = $repo;
        $this->repo_travel_user = $repo_travel_user;
        $this->repo_master_roles = $repo_master_roles;
        $this->repo_role_user = $repo_role_user;
        $this->repo_menu_roles = $repo_menu_roles;

    }


    public function saveConfig(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|exists:users,id',
            'roles' => 'required|array|min:1',
            'travel' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 422);   // ← diperbaiki (standard Laravel)
        }

        $userId = $request->user_id;
        $roles = $request->roles ?? [];
        $travels = $request->travel ?? [];

        DB::beginTransaction();

        try {
            // ====================== ROLES ======================
            if (count($roles) > 0) {
                // Hapus SEMUA role lama user ini (1 query saja)
                $this->repo_role_user->whereData(['user_id' => $userId])->delete();

                // Insert role baru
                foreach ($roles as $roleId) {
                    $this->repo_role_user->CreateOrUpdate([
                        'user_id' => $userId,
                        'role_id' => $roleId,
                    ], null);
                }
            }

            // ====================== TRAVEL ======================
            // Selalu hapus travel lama (kalau ada data baru atau tidak)
            $this->repo_travel_user->whereData(['user_id' => $userId])->delete();

            if (count($travels) > 0) {
                foreach ($travels as $travelId) {
                    $this->repo_travel_user->CreateOrUpdate([
                        'user_id' => $userId,
                        'travel_id' => $travelId,
                    ], null);
                }
            }

            DB::commit();

            return $this->success([
                'message' => 'Konfigurasi user berhasil disimpan',
                'user_id' => $userId,
                'roles' => $roles,
                'travel' => $travels,
            ]);

        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->error("Gagal menyimpan data: " . $th->getMessage(), 500);
        }
    }


    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            // 'account_type' => 'required|string|regex:/^(current_asset|fixed_asset|revenue|inventory|non_current_asset|prepayment|equity|description|direct_cost|expense|overhead|current_liability|liability|non_current_liability|other_income|sales)$/i',
            'name' => [
                'required',
                'string',
                //'regex:/^(current_asset|fixed_asset|revenue|inventory|non_current_asset|prepayment|equity|description|direct_cost|expense|overhead|current_liability|liability|non_current_liability|other_income|sales)$/i'
            ],
            'email' => 'required|email',
            'password' => 'required|string'

        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 400);
        }

        $request->merge(['password' => Hash::make($request->password)]);

        $saved = $this->repo->CreateOrUpdate($request->all(), $request->id);
        return $this->autoResponse($saved);
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
        //dd($request->menu);
        $where = [];
        if ($request->keyword != null) {
            $data = $this->repo->searchData($where, $request->limit, $request->page, 'name', strtoupper($request->keyword));
        } else {
            $data = $this->repo->getAllDataWithDefault($where, $request->limit, $request->page, 'name', 'ASC');//getDataPaginate("name",10,$request->keyword);
        }
        //$data['menu']= $request->menu;
        return $this->autoResponse($data);
        //return $this->success($data);
    }



    public function delete(Request $request)
    {
        $id = $request->id;

        $validator = Validator::make($request->all(), [
            'id' => 'required|numeric|exists:users,id',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 404);
        }

        $data = $this->repo->delete($id);
        return $this->autoResponse($data);

    }


    public function detail(Request $request)
    {
        //$data = $this->repo->find($request->id);
        $role_users = RoleUsers::where('user_id', $request->id)->pluck('role_id');
        $roles_menu = RoleMenus::whereIn('role_id', $role_users)->pluck('menu_id');

        $view_menu = Menu::whereIn('id', $roles_menu)
            ->where('is_active', 1)
            // ->where('parent_id', null)
            ->orderBy('urutan', 'asc')->get();

        $data = $this->repo->find($request->id);
        $data['menu'] = $view_menu;
        $data['role_user'] = $role_users;
        $data['travel_user_all'] = TravelUser::where('user_id', $request->id)->get();
        //$data['travel'] = $this->repo->WhereDataWith('travelUsers',['id'=>$request->id])->first();// User::with('')->where()->first();
        return $this->autoResponse($data);
    }

    //
}
