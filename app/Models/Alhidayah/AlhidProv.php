<?php

namespace App\Models\Alhidayah;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AlhidProv extends Model
{
    use HasFactory;

    protected $connection = 'mysql_2';

    protected $table = 'location_provinces';

    protected $fillable = ["id", "name"];


}
