<?php

namespace App\Models\MasterData;

use App\Models\Transaction\TransactionAllCoa;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Coa extends Model
{
    use HasFactory;


    protected $fillable = [
        'code',
        'account_type',//string tidak bebas,
        //asset : CURRENT, FIXED, BANK
        //liabillities : CURRLIAB
        //equility : EQUITY
        //expenses : EXPENSE, DIRECTCOSTS
        //revenue : OTHERINCOME, REVENUE
        //archive : -> tidak di pake
        'name',
        'created_by',
        'desc',
        'account_uuid',
        'is_active',
        'currency_code'
    ];

    //coa yang di hide pada xero :
    //Realised Currency Gains (Keuntungan kurs terealisasi)
    // Unrealised Currency Gains (Keuntungan/kerugian kurs belum terealisasi)
    // Bank Currency Revaluation (Revaluasi kurs bank)
    // Unpaid Expense Claims (Klaim pengeluaran yang belum dibayar)
    // Historical Adjustments (Penyesuaian historis/saldo awal)
    // Rounding (Akun pembulatan)
    // Tracking Transfers (Akun sistem untuk mutasi tracking category)

    protected $appends = [
        'sum_nominal'
    ];

    public function getTrans()
    {
        return $this->hasMany(TransactionAllCoa::class, 'id', 'uuid_coa');
    }

    public function getSumNominalAttribute()
    {
        return TransactionAllCoa::where('uuid_coa', $this->id)->sum('nominal');
    }
}
