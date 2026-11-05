<?php

namespace App\Models\Transaction;

use App\Models\InvoicesAllFromXero;
use App\Models\MasterData\BankXero;
use App\Models\MasterData\DataJamaahXero;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Overpayment extends Model
{
    use HasFactory;
    //yang di simpan nominal rp (yg sudah di conversi), 
    /*saat pembayaran di bank transfer dan terjadi kelebihan masuk ke tabel ini,
    where =  invoice_id & jamaah_contact_id
    */
    protected $fillable = [
        'nominal_overpayment',
        'invoice_id',
        'bills_id',
        'bank_id',//master bank tujuan
        'trans_bank_id',//id transaction_nominal_bank_accounts
        'jamaah_contact_id'
    ];

    protected $appends = [
        'inv_number',
        'name_contact',
        'bank_name'
    ];


    public function getBankNameAttribute()
    {
        return optional($this->getBank)->name ?? 'no name';
    }

    public function getNameContactAttribute()
    {
        return $this->getJamaah->full_name ?? '-';
    }

    public function getJamaah()
    {
        return $this->hasOne(DataJamaahXero::class, 'id', 'jamaah_contact_id');
    }

    public function historyPengembalian()
    {
        return $this->hasMany(FeeOverpay::class, 'overpay_id', 'id');
    }

    public function getBank()
    {
        return $this->hasOne(BankXero::class, 'id', 'bank_id');
    }

    public function getInv()
    {
        return $this->hasOne(InvoicesAllFromXero::class, 'id', 'invoice_id');
    }

    public function getInvNumberAttribute()
    {
        return $this->getInv->invoice_number ?? '-';
    }

    protected static function booted()
    {
        static::updating(function ($m) {
            if ($m->isDirty('nominal_overpayment')) {
                \Log::info('[overpay-debug]', [
                    'id' => $m->id,
                    'old' => $m->getOriginal('nominal_overpayment'),
                    'new' => $m->nominal_overpayment,
                    'trace' => collect(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 15))
                        ->map(fn($t) => ($t['class'] ?? '') . '::' . ($t['function'] ?? '') . ':' . ($t['line'] ?? ''))
                        ->all(),
                ]);
            }
        });
    }
}
