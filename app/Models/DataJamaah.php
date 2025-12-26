<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DataJamaah extends Model
{
    //

     protected $connection = 'mysql_2';
    protected $table = 'data_jamaah';
    public $timestamps = false;


     protected $fillable = [
        "is_updated_to_xero"
     ];
}
