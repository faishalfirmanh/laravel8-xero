<?php

namespace App\Models\Transaction;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SpendMoneyXero extends Model
{
    use HasFactory;



    protected $fillable = [
        'to',
        'date',
        'reference',
        'currency_code',
        'lines',          // JSON array untuk baris-baris item
        'subtotal',
        'tax',
        'total',
        'status'          // optional (1 = draft, 2 = posted, dll)
    ];

    protected $casts = [
        'date'      => 'date',
        'lines'     => 'array',
        'subtotal'  => 'decimal:2',
        'tax'       => 'decimal:2',
        'total'     => 'decimal:2',
    ];


}
