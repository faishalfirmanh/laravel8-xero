<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoicesAllFromXero extends Model
{
    use HasFactory;

     protected $fillable = [
        'invoice_number',
        'invoice_uuid',
        'invoice_amount',//invoice paid
        'invoice_total',//total yang harus di bayarkan
        'uuid_proudct_and_service',
        'issue_date',
        'due_date',
        'status',
        'uuid_contact',
        'contact_name',
        'item_name'
    ];

}
