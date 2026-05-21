<?php

namespace App\Models\MasterData;

use App\Models\Transaction\TransactionNominalBankAccount;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BankXero extends Model
{
    use HasFactory;

    protected $fillable = [

        'account_id',
        'code',
        'name',
        'status',//ACTIVE = 1,
        'type',
        'currency_code',
        'account_number'

    ];

    protected $appends = [
        'sum_receive',
        'sum_spend',
    ];


    public function nominalTransactions()
    {
        return $this->hasMany(TransactionNominalBankAccount::class, 'uuid_bank', 'id');
    }

    // 2. Buat Accessor untuk mendapatkan nilai "sum_receive"
    public function getSumReceiveAttribute()
    {
        return $this->nominalTransactions()->sum('nominal_receive');
    }
    public function getSumSpendAttribute()
    {
        return $this->nominalTransactions()->sum('nominal_spend');
    }
}
