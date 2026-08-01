<?php

namespace App\Models\Transaction;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VaTransUser extends Model
{
    use HasFactory;

    protected $fillable = [
        'inv_number',
        'va_number',
        'paket_name',
        'bank_name',
        'name_contact',
        'payment',
        'total_nominal'
    ];

}
