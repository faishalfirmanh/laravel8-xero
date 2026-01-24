<?php

namespace App\Http\Repository\Revenue;

use App\Http\Repository\BaseRepository;
use App\Models\Revenue\Hotel\InvoicesHotel;

use DB;

class HotelInvoicesRepository extends BaseRepository
{

    public $model;
    public function __construct(InvoicesHotel $model)
    {
        $this->model = $model;
    }

}
