<?php

namespace App\Models\Transaction;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransactionBankTransP extends Model
{
    use HasFactory;


    protected $fillable = [
        'uuid_to',
        'date_h',
        'reference',
        'amounts_are',
        'created_by',
        'tax',
        'subtotal',
        'total',
    ];
}
