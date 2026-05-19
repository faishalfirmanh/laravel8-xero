<?php

namespace App\Models\MasterData;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BankXero extends Model
{
    use HasFactory;

    protected $fillable = [

        'account_id',
        'code',
        'name',
        'status',//ACTIVE = 1,
        'type',
        'currency_code',
        'account_number'

    ];
}
