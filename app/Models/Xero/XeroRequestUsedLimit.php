<?php

namespace App\Models\Xero;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class XeroRequestUsedLimit extends Model
{
    use HasFactory;
     protected $fillable = [
        'total_request_used_min',
        'total_request_used_day',
        'available_request_min',
        'available_request_day',
        'tracking_date'
    ];
}
