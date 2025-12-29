<?php

namespace App\Models\MasterData\Location;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subdistrict extends Model
{
    use HasFactory;

    protected $table = 'location_districts';

    protected $fillable = ["id","kabupaten_id","name"];
}
