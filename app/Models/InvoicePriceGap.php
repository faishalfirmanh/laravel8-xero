<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoicePriceGap extends Model
{
    use HasFactory;

     protected $fillable = [
        "invoice_number",
        "invoice_uuid",
        "contact_name",
        "total_nominal_payment_xero",
        "total_nominal_payment_local",
        "total_price_return"
     ];
}
