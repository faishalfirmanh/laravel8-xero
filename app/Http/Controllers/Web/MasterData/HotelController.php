<?php

namespace App\Http\Controllers\Web\MasterData;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Validator;
use App\Traits\ApiResponse;

class HotelController extends Controller
{

    public function index()
    {
        return view('admin.master.hotel');
    }

}
