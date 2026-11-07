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

    //yang di simpan nominal rp (yg sudah di conversi)
    /*saat pengembalian dana create pertama  akan insert di 3 tabel 
    1. fee_overpays 
    2. transaction_nominal_bank_accounts ->bank spend
      -nominal_spend, date_transaction, id_parent_invoice, id_fee_overpay
    3. transaction_all_coas -> code 4800 (DISKON PENJUALAN (KOMPENSASI) - item pengembalian biaya 
    4. overpayments (update nominal_overpayment )
    5. summary_nominal_banks update in,out dan final_nominal
    */
    protected $fillable = [
        'date_transaction',
        'invoice_id',
        'bank_id_origin',//bank asal untuk di keluarkan
        'nominal',
        'bank_tujuan_jamaah_rek',//referensinya input bebas
        'reference',
        'account_id',//coas dengan code 8400 diskon kusus 
        'overpay_id',//reference pada tabel overpayment, jadi total where invoice_id & overpay_id tidak boleh > overpaint (kolom nominal_overpayment)
        'jamaah_id',
        'uuid_details_feeoverpay'//untuk relasi dengan trans coa, seperti detail bill, invoice,bank
    ];

    protected $appends = [
        'name_bank',
        'name_jamaah_invoice'
    ];

    public function getNameBankAttribute()
    {
        return optional($this->getBankOrigin)->name ?? '-';
    }

    public function getNameJamaahInvoiceAttribute()
    {
        return optional($this->getInvoice)->contact_name ?? '-';
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
