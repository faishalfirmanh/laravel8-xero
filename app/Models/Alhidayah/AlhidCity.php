<?php

namespace App\Models\Alhidayah;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AlhidCity extends Model
{
    use HasFactory;

    protected $connection = 'mysql_2';

    protected $table = 'location_city';
    protected $fillable = ["id", "id_prov", "name"];

}
