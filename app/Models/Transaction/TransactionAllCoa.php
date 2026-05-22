<?php

namespace App\Models\Transaction;

use App\Models\Expenses\Purchase\Bill\DBill;
use App\Models\MasterData\Coa;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransactionAllCoa extends Model
{
    use HasFactory;
    //yang di save parentnya, semua transaksi ter record disini


    //Real uang ada di bank (master bank)
    //Coa sebagai tampungan semua transaksi bisa di sebut sebagai history untuk laporan & backup

    protected $fillable = [
        'date_transaction',
        'uuid_coa',//id coa, coas->id
        'reference',
        'is_speend',
        'nominal',
        'uuid_detail',//bisa relasi bank, bisa bill, bisa invoice
        'created_by'
    ];

    public $appends = ['name_trans', 'name_coa'];

    public function getNameTransAttribute()
    {
        return optional($this->d_bill)->getParent->name_contact ?? '-';
    }

    public function getNameCoaAttribute()
    {
        return optional($this->getCoa)->name ?? '-';
    }

    public function d_bill()
    {
        return $this->hasOne(DBill::class, 'uuid_detail', 'uuid_detail');
    }

    public function d_bank()
    {
        return $this->hasOne(TransactionBankTransD::class, 'uuid_detail_trans_bank', 'uuid_detail');
    }

    public function getCoa()
    {
        return $this->hasOne(Coa::class, 'id', 'uuid_coa');
    }
}
