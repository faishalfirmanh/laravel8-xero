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
        'bank_id_xero'
    ];

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
