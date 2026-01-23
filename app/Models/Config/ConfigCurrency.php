<?php

namespace App\Models\Config;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConfigCurrency extends Model
{
    use HasFactory;

     protected $fillable = [
        'nominal_rupiah_1_riyal'
     ];
}
