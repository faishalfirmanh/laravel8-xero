<?php

namespace App\Models\Transaction;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransactionNominalBankAccount extends Model
{
    use HasFactory;


    protected $fillable = [
        'uuid_bank',
        'account_transaction',
        'nominal',
        'created_by',
    ];
}
