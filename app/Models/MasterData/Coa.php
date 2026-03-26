<?php

namespace App\Models\MasterData;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Coa extends Model
{
    use HasFactory;


    protected $fillable = [
        'code',
        'account_type',//string tidak bebas
        'name',
        'created_by',
       'desc'
    ];
}
