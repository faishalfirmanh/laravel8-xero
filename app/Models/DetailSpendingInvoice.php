<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DetailSpendingInvoice extends Model
{
    use HasFactory;


     protected $fillable = [
        'invoice_uuid',
        'nominal',
        'uuid_paket_xero',
        'id_master_pengeluaran',
        'paket_uuid'
    ];

    
}
