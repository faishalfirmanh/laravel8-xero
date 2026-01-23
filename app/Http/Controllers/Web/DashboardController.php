<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

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


    public function getWebListInvoice()
    {
        return view('admin.list_invoices');
    }

    public function getWebListPengeluaran()
    {
        return view('admin.list_pengeluaran');
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

}
