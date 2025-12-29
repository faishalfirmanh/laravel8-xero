<?php

namespace App\Models\MasterData\Location;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class City extends Model
{
    use HasFactory;
    protected $table = 'location_city';

    protected $fillable = ["id","id_prov","name"];
}
