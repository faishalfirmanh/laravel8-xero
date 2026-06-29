<?php

namespace App\Models\Transaction;

use App\Models\MasterData\BankXero;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SummaryNominalBank extends Model
{
    use HasFactory;


    protected $fillable = [
        'bank_id_from',
        'bank_id_to',
        'date_trans',
        'amount',
        'reference_transfer_bank',
        'code_tracking_paket_from',
        'code_tracking_divisi_from',
        'code_tracking_paket_to',
        'code_tracking_divisi_to',
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
