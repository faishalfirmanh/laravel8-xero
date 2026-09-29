<?php

namespace App\Models\Transaction;

use App\Models\MasterData\DataJamaahXero;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransactionBankTransP extends Model
{
    use HasFactory;


    protected $fillable = [
        'uuid_to',
        'date_h',
        'reference',
        'amounts_are',//tax exclude = 2, tax inclusive = 1, no tax = 0
        'created_by',
        'tax',
        'subtotal',
        'total',
        'is_spend',
        'bank_id_xero',
        'id_jamaah_alhid',
        'uuid_bank_trans',//unutk ambil dari sync xero, nullable
        'status'//0 : direct, 1 : prepayment, 2: overpay
    ];
    // RECEIVE	: Uang masuk biasa yang tidak terkait invoice	\ dipake
// SPEND	: Uang keluar biasa yang tidak terkait bill	\ dipake
// RECEIVE-OVERPAYMENT	 : Pelanggan membayar lebih dari nilai invoice, kelebihannya jadi saldo kredit pelanggan	Invoice Rp 10 jt, pelanggan transfer Rp 12 jt, sisa Rp 2 jt jadi overpayment
// RECEIVE-PREPAYMENT	: Pelanggan membayar di muka, sebelum invoice dibuat	DP atau tanda jadi, misalnya DP paket umrah sebelum invoice terbit
// SPEND-OVERPAYMENT	: Kita membayar supplier lebih dari nilai bill	Kebalikan dari RECEIVE-OVERPAYMENT
// SPEND-PREPAYMENT :	Kita membayar supplier di muka sebelum ada bill	Uang muka ke vendor
// SPEND-TRANSFER	: Sisi keluar dari transfer antar rekening bank milik sendiri	Kas Kasir dipindah ke rekening BCA
// RECEIVE-TRANSFER	: Sisi masuk dari transfer yang sama	Rekening BCA menerima dari Kasir
    protected $appends = [
        'name_contact_trans_bank',
        'nama_pembuat'
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getNamaPembuatAttribute()
    {
        return optional($this->creator)->name ?? 'no name';
    }

    public function getNameContactTransBankAttribute()
    {
        return optional($this->getContactFrom)->full_name ?? 'no name';
    }

    public function getContactFrom()
    {
        return $this->hasOne(DataJamaahXero::class, 'id', 'uuid_to');
    }

    public function getDetail()
    {
        return $this->hasMany(TransactionBankTransD::class, 'trans_bank_parent_id');
    }
}
