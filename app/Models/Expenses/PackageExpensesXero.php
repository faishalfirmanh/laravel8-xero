<?php

namespace App\Models\Expenses;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PackageExpensesXero extends Model
{
    use HasFactory;

    protected $fillable =[
        'uuid_paket_item',
        'code_paket',
        'name_paket',
        'nominal_purchase',//modal
        'nominal_sales',//jual
        'nominal_profit',
        'created_by',
    ];
}
