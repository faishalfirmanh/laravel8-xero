<?php

namespace App\Models\Transaction;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Overpayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'nominal_overpayment',
        'invoice_id',
        'bills_id',
        'bank_id',
        'trans_bank_id'
    ];
}
