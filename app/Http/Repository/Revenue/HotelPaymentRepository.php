<?php

namespace App\Http\Repository\Revenue;

use App\Http\Repository\BaseRepository;
use App\Models\Revenue\Hotel\PaymentHotels;

use DB;

class HotelPaymentRepository extends BaseRepository
{

    public $model;
    public function __construct(PaymentHotels $model)
    {
        $this->model = $model;
    }

}
