<?php

namespace App\Models\Transaction;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransactionBankTransD extends Model
{
    use HasFactory;


    protected $fillable = [

        'trans_bank_parent_id',
        'item_code',
        'desc',
        'qty',
        'unit_price',
        'account_id_coa',
        'tax_rate',
        'paket_tracking_uuid',
        'divisi_travel_tracking_uuid',
        'amount',
        'uuid_detail_trans_bank'
    ];


    public function getParent()
    {
        return $this->belongsTo(TransactionBankTransP::class, 'trans_bank_parent_id', 'id');
    }
}
