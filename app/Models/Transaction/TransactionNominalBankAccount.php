<?php

namespace App\Models\Transaction;

use App\Models\Expenses\Purchase\Bill\PBill;
use App\Models\InvoicesAllFromXero;
use App\Models\ItemsPaketAllFromXero;
use App\Models\MasterData\BankXero;
use App\Models\MasterData\Coa;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransactionNominalBankAccount extends Model
{
    use HasFactory;


    //saat transaksi tabel ini, harus manggil update tabel ini juga : SummaryNominalBank
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
        'id_parent_bank',//relation with TransactionBankTransP ->id,saat receive dan spend money
        'id_parent_invoice',
        'id_fee_overpay',//relasi fee_overpay jika uang di kembalikan
        'payment_uuid',//unique key id,
        'trans_transfer_bank_id',//untuk transaksi bank transfer,
        'overpay_id',//terisi jika pembayarna lewat overpayment, jika bayar dengan overpay
        //overpay yang wajib terisi : uuid_bank, nominal_spend, date_transaction,reference_detail,overpay_id
        'nominal_currency',//nominal currency ke rupiah
        'total_base_spend',//hasil final conversi nominal_speend 
        'total_base_receive',//hasil final conversi nominal_receive 
        // 'id_bank_transfer' //relasi dengan id_bank_transfer jika ada bank trasnfer,
        'item_id_for_payment',//untuk pembayaran untuk item paket apa , jika pemayarna paket wajib diisi
    ];

    protected $appends = [
        'name_bank',
        'nama_paket_payment'
    ];

    public function getNameBankAttribute()
    {
        return optional($this->getBank)->name ?? '-';
    }

    public function getNamaPaketPaymentAttribute()
    {
        return optional($this->getItemPaket)->nama_paket ?? '-';
    }

    public function getBankTransfer()
    {
        return $this->belongsTo(TransactionTransferBank::class, 'id', 'trans_transfer_bank_id');
    }

    public function getItemPaket()
    {
        return $this->belongsTo(ItemsPaketAllFromXero::class, 'item_id_for_payment');
    }

    public function getPaketName()
    {
        return $this->hasOne(ItemsPaketAllFromXero::class, 'id', 'item_id_for_payment');
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

    public function getFeeOver()
    {
        return $this->hasOne(FeeOverpay::class, 'id', 'id_fee_overpay');
    }
}
