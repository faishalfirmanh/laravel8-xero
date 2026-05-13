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


    protected $fillable = [
        'date_transaction',
        'uuid_coa',//id coa, coas->id
        'reference',
        'is_speend',
        'nominal',
        'uuid_detail',
        'created_by'
    ];

    public function d_bill()
    {
        return $this->hasOne(DBill::class, 'uuid_detail');
    }

    public function getCoa()
    {
        return $this->hasOne(Coa::class, 'uuid_coa');
    }
}
