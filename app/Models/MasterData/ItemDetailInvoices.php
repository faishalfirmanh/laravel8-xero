<?php

namespace App\Models\MasterData;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ItemDetailInvoices extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_number',
        'uuid_invoices',
        'uuid_item',
        'qty',
        'unit_price',
        'total_amount_each_row'
    ];
}
