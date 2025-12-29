<?php

namespace App\Models\MasterData\Location;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Villages extends Model
{
    use HasFactory;

    protected $table = 'location_villages';

     protected $fillable = ["id","id_kecamatan","name"];
}
