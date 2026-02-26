<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PrepaymentInvoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_number',
        'contact_name',
        'invoice_uuid',
        'prepayment_uuid',
        'date',
        'amount',
    ];
}
