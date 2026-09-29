<?php

namespace App\Models\Transaction;

use App\Models\MasterData\BankXero;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SummaryNominalBank extends Model
{
    use HasFactory;


    protected $fillable = [
        'bank_id',
        'nominal_in',
        'nominal_out',
        'final_nominal',
    ];

    protected $appends = [
        'bank_name'
    ];

    public function getBankNameAttribute()
    {
        return optional($this->getBank)->name ?? 'no name';
    }


    public function getBank()
    {
        return $this->hasOne(BankXero::class, 'id', 'bank_id');
    }
}
