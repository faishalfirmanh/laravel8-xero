<?php

namespace App\Http\Repository\Revenue;

use App\Http\Repository\BaseRepository;
use App\Models\Revenue\Hotel\DetailInvoicesHotel;


use DB;

class HotelDetailInvoicesRepository extends BaseRepository
{

    public $model;
    public function __construct(DetailInvoicesHotel $model)
    {
        $this->model = $model;
    }

}
