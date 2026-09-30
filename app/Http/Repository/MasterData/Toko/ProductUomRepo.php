<?php

namespace App\Http\Repository\MasterData\Toko;

use App\Http\Repository\BaseRepository;
use App\Models\MasterData\Toko\ProductUom;

class ProductUomRepo extends BaseRepository
{
    public function __construct(ProductUom $model)
    {
        parent::__construct($model);
    }
}
