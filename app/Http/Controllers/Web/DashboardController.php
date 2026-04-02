<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\MasterData\Menu;
use App\Models\MasterData\MasterRoleUser;
use App\Models\MasterData\TravelName;
use App\Models\MasterData\BusinessLine;
use Validator;
use App\Traits\ApiResponse;

class DashboardController extends Controller
{
    public function index()
    {
        // Contoh data dummy untuk Select2
        $data['agen_list'] = [
            ['id' => 1, 'nama' => 'Agen Jakarta'],
            ['id' => 2, 'nama' => 'Agen Bandung'],
            ['id' => 3, 'nama' => 'Agen Surabaya'],
        ];

        return view('admin.dashboard', $data);
    }

    public function viewListInvXeroLocal()
    {
       return view('admin.transaksi.sales.list_inv_from_xero');
    }


    public function getWebListInvoice()
    {
        return view('admin.list_invoices');
    }

    public function getWebListPengeluaran()
    {
        return view('admin.master.list_pengeluaran');
    }

    public function getWebListPembelianHotel()
    {
        return view('admin.transaksi.pembelian_hotel');
    }

    public function getConfigCurrency()
    {
        return view('admin.config.config_currency');
    }

    public function getJamaah()
    {
        return view('admin.master.jamaah');
    }

      public function getHotelSalesList()
     {
        return view('admin.master.hotel_sales');
     }

      public function getTrackingKategoryist()
     {
        return view('admin.master.tracking');
     }

      public function getCoaList()
     {
        return view('admin.master.coa');
     }

     public function getBusiness()
     {
          return view('admin.master.business');
     }

      public function getTravel()
     {
        return view('admin.master.travel');
     }

     public function getConfigRoleUser()
     {
        $get_menu = Menu::orderBy('urutan','asc')->get();
        $get_divisi = MasterRoleUser::orderBy('id','desc')->get();
        $get_all_travel = TravelName::orderBy('id','desc')->get();
        return view('admin.config.config_role',['menu_list'=>$get_menu,'get_divisi'=>$get_divisi,'travel'=>$get_all_travel]);
     }

}
