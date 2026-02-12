<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentsHistoryFix extends Model
{
    //

     protected $fillable = [
        'invoice_number',
        'contact_name',
        'invoice_uuid',
        'payment_uuid',
        'date',
        'amount',
        'reference',
        'name_bank_transfer',
        'account_uuid_or_bank'
    ];
}
