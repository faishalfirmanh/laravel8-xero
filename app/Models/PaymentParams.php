<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentParams extends Model
{
    //

 protected $fillable = [
        'invoice_id',
        'account_code',
        'date',
        'amount',
        'reference',
        'payments_id',
        'bank_account_id'
    ];

}
