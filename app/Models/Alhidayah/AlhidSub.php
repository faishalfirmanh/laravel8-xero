<?php

namespace App\Models\Alhidayah;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AlhidSub extends Model
{
    use HasFactory;

    protected $connection = 'mysql_2';//kecamatan

    protected $table = 'location_districts';

    protected $fillable = ["id", "kabupaten_id", "name"];


}
