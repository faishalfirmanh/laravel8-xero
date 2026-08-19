<?php

namespace App\Models\Transaction;

use App\Models\InvoicesAllFromXero;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Overpayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'nominal_overpayment',
        'invoice_id',
        'bills_id',
        'bank_id',//master bank tujuan
        'trans_bank_id',//id transaction_nominal_bank_accounts
        'jamaah_contact_id'
    ];

    protected $appends = [
        'inv_number'
    ];

    public function getInv()
    {
        return $this->hasOne(InvoicesAllFromXero::class, 'id', 'invoice_id');
    }

    public function getInvNumberAttribute()
    {
        return $this->getInv->invoice_number;
    }
}
