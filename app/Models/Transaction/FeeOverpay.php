<?php

namespace App\Models\Transaction;

use App\Models\InvoicesAllFromXero;
use App\Models\MasterData\BankXero;
use App\Models\MasterData\Coa;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FeeOverpay extends Model
{
    use HasFactory;

    protected $fillable = [
        'date_transaction',
        'invoice_id',
        'bank_id_origin',
        'nominal',
        'bank_tujuan_jamaah_rek',
        'reference',
        'account_id',//diskon
        'overpay_id',
        'jamaah_id',
        'uuid_details_feeoverpay'//untuk relasi dengan trans coa, seperti detail bill, invoice,bank
    ];

    protected $appends = [
        'name_bank'
    ];

    public function getNameBankAttribute()
    {
        return optional($this->getBankOrigin)->name ?? '-';
    }



    public function getOverpayParent()
    {
        return $this->belongsTo(Overpayment::class, 'overpay_id', 'id');
    }

    public function getBankOrigin()
    {
        return $this->belongsTo(BankXero::class, 'bank_id_origin', 'id');
    }

    public function getCoa()
    {
        return $this->belongsTo(Coa::class, 'account_id', 'id');
    }

    public function getInvoice()
    {
        return $this->belongsTo(InvoicesAllFromXero::class, 'invoice_id', 'id');
    }

}
