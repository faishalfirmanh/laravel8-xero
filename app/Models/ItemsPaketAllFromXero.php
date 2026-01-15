<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ItemsPaketAllFromXero extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid_proudct_and_service',
        'code',
        'nama_paket',
        'purchase_AccountCode',
        'sales_AccountCode',
        'total_hari'
    ];
}
