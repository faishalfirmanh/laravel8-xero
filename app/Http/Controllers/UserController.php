<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Http\Repository\MasterData\CoaRepo;

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
    protected $repo, $repo_travel_user;

    public function __construct(UserRepository $repo,TravelUserRepository $repo_travel_user)
    {
        $this->repo = $repo;
        $this->repo_travel_user = $repo_travel_user;

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
            'email'=> 'required|email',
            'password'=> 'required|string'

        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(),400);
        }

        $request->merge(['password'=>Hash::make($request->password)]);

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
        $roles_menu = RoleMenus::whereIn('role_id',$role_users)->pluck('menu_id');
        $view_menu = Menu::with('children')->whereIn('id',$roles_menu)
            ->where('is_active',1)
            ->where('parent_id',null)
            ->orderBy('urutan','asc')->get();

        $data = $this->repo->find($request->id);
        $data['menu']= $view_menu;
        $data['role_user'] =$role_users;
        //$data['travel'] = $this->repo->WhereDataWith('travelUsers',['id'=>$request->id])->first();// User::with('')->where()->first();
        return $this->autoResponse($data);
    }

//
}
