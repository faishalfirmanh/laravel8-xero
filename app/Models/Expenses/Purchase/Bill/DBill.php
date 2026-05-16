<?php

namespace App\Models\Expenses\Purchase\Bill;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DBill extends Model
{
    use HasFactory;

    protected $fillable = [
        'bills_parent_id',
        'item_code',
        'desc',
        'qty',
        'unit_price',
        'account_id_coa',//pake id saja
        'tax_rate',
        'paket_tracking_uuid',//tracking_categories
        'divisi_travel_tracking_uuid',
        'amount',
        'uuid_detail'//untu relasi dengan tabel transaction_all_coas
    ];

    public function getParent()
    {
        return $this->belongsTo(PBill::class, 'bills_parent_id', 'id');
    }
}
