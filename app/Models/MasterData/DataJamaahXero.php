<?php

namespace App\Models\MasterData;

use App\Models\Transaction\Overpayment;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Revenue\Hotel\InvoicesHotel;

class DataJamaahXero extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid_contact',
        'full_name',
        'phone_number',
        'is_jamaah',
        'is_agen',
        'is_mitra_trevel',
        'username',
        'pass',
        'nik',
        'detail_address',
    ];

    public $appends = [
        'tot_over'
    ];

    public function transHotel()
    {
        return $this->hasMany(InvoicesHotel::class, 'uuid_user_order', 'uuid_contact');
    }

    public function overPay()
    {
        //1 data_jamaah_xero bisa memiliki : overpaymetn > 1
        return $this->hasMany(Overpayment::class, 'jamaah_contact_id', 'id');
    }

    public function listAllOverpay()
    {
        return $this->hasMany(Overpayment::class, 'jamaah_contact_id');
    }

    public function getTotOverAttribute()
    {
        return $this->overPay()->sum('nominal_overpayment');
    }
}
