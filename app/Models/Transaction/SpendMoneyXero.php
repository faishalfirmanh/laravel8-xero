<?php

namespace App\Models\Transaction;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SpendMoneyXero extends Model
{
    use HasFactory;


    //untuk spend dan recive money

    protected $fillable = [
        'type_trans',//1 = spend money, 2=receive money
        'to',
        'date',
        'reference',
        'currency_code',
        'lines',          // JSON array untuk baris-baris item
        'subtotal',
        'tax',
        'total',
        'status',          // optional (1 = draft, 2 = posted, dll)
        'contact_id',
        'bank_id',
        'tax_type',//1=no tax, 2, tax inclusive, 3, tax exclusive
    ];

    protected $casts = [
        'date'      => 'date',
        'lines'     => 'array',
        'subtotal'  => 'decimal:2',
        'tax'       => 'decimal:2',
        'total'     => 'decimal:2',
    ];


}
