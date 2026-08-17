<?php

namespace App\Models\Alhidayah;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AlhidVill extends Model
{
    use HasFactory;

    protected $connection = 'mysql_2';

    protected $table = 'location_villages';

    protected $fillable = ["id", "id_kecamatan", "name"];

}
