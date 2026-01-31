<?php

namespace App\Models\MasterData;

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
        'is_mitra_trevel'
    ];

    public function transHotel()
    {
          return $this->hasMany(InvoicesHotel::class, 'uuid_user_order','uuid_contact');
    }
}
