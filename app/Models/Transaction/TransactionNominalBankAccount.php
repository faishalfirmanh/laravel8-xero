<?php

namespace App\Models\Transaction;

use App\Models\Expenses\Purchase\Bill\PBill;
use App\Models\InvoicesAllFromXero;
use App\Models\MasterData\BankXero;
use App\Models\MasterData\Coa;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransactionNominalBankAccount extends Model
{
    use HasFactory;


    protected $fillable = [
        'uuid_bank',
        'account_transaction',
        'nominal_receive',//nominal -> asal nominal,
        'nominal_spend',
        'nominal_transfer',
        'reference_detail',
        'id_parent_invoice',
        'id_parent_bill',
        'date_transaction',
        'created_by',
        'id_parent_bank',//relation with TransactionBankTransP ->id,
        'id_parent_invoice'
    ];

    protected $appends = [
        'name_bank'
    ];

    public function getNameBankAttribute()
    {
        return optional($this->getBank)->name ?? '-';
    }

    public function getBank()
    {
        return $this->hasOne(BankXero::class, 'id', 'uuid_bank');
    }

    public function getCoa()
    {
        return $this->hasOne(Coa::class, 'id', 'account_transaction');
    }


    public function getPbill()
    {
        return $this->hasOne(PBill::class, 'id', 'id_parent_bill');
    }

    public function getInv()
    {
        return $this->hasOne(InvoicesAllFromXero::class, 'id', 'id_parent_invoice');
    }

    public function getPBank()
    {
        return $this->hasOne(TransactionBankTransP::class, 'id', 'id_parent_bank');
    }
}
