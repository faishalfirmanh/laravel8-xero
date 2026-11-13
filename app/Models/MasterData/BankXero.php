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
        'final_nominal',
        'sum_curr_spend',
        'sum_curr_receive',
        'final_nominal_currency'
    ];

    //Transaksi banak hubungnnya
    //summary_nominal_banks : bank_id
    //transaction_nominal_bank_accounts ->uuid_bank (1 bank_xeros banyak -> transaction_nominal_bank_accounts) ->bank_xeros->id
    //invoices_all_from_xeros : id_parent_invoice 
    //p_bills : id_parent_bill 
    //fee_overpays : id_fee_overpay 
    //transaction_bank_trans_p_s :id_parent_bank ,
    //transaction_transfer_banks :trans_transfer_bank_id

    public function getSumCurrSpendAttribute()
    {
        return $this->nominalTransactions()->sum('total_base_spend');
    }


    public function getSumCurrReceiveAttribute()
    {
        return $this->nominalTransactions()->sum('total_base_receive');
    }

    public function getFinalNominalCurrencyAttribute()//untuk mata uang
    {
        return $this->sum_curr_receive - $this->sum_curr_spend;
    }

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
    public function getFinalNominalAttribute()
    {
        return $this->sum_receive - $this->sum_spend;
    }
}
