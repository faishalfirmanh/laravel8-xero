<?php

namespace App\Models\Expenses\Purchase\Bill;

use App\Models\MasterData\DataJamaahXero;
use App\Models\Transaction\TransactionNominalBankAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PBill extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid_from',//id dari tabel jamaah
        'date_req',
        'due_date',
        'reference',
        'amounts_are',
        'subtotal',
        'total',
        'tax',
        'nominal_paid',
        'nominal_due',
        'status',//0=draft,1=awaiting,  2=paid,
        'currency',
        'created_by'
    ];
    //status 0 /draft tidak tercatat pada

    protected $appends = [
        'name_contact_bill',
        'nama_pembuat'
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }


    public function getPayment()
    {
        return $this->hasMany(TransactionNominalBankAccount::class, 'id_parent_bill');
    }

    public function getNamaPembuatAttribute()
    {
        return optional($this->creator)->name ?? 'no name';
    }

    public function getNameContactBillAttribute()
    {
        return optional($this->getContactFrom)->full_name ?? 'no name';
    }

    public function getContactFrom()
    {
        return $this->hasOne(DataJamaahXero::class, 'id', 'uuid_from');
    }

    public function getDetail()
    {
        return $this->hasMany(DBill::class, 'bills_parent_id');
    }

}
