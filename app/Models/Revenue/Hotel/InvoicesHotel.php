<?php

namespace App\Models\Revenue\Hotel;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Revenue\Hotel\PaymentHotels;
use App\Models\Revenue\Hotel\DetailInvoicesHotel;
use App\Models\MasterData\Hotel;

class InvoicesHotel extends Model
{
    use HasFactory;

    protected $appends = [
     'hotel_name'
    ];

    protected $fillable =
    [
        'no_invoice_hotel',
        'uuid_user_order',
        'hotel_id',
        'nama_pemesan',
        'check_in',
        'check_out',
        'total_days',
        'total_payment',//Sar,//total amount
        'total_payment_rupiah',//total amount
        'date_transaction',
        'created_by',
        'status',
        'final_payment_sar',
        'final_payment_idr',
        'less_payment_idr',
        'less_payment_sar'
    ];

    public function getHotelNameAttribute()
    {
        $data = Hotel::find($this->hotel_id);
        return $data->name;
    }




      public function details()
    {
        return $this->hasMany(DetailInvoicesHotel::class, 'invoice_id');
    }

    public function hotel()
    {
        return $this->hasMany(Hotel::class, 'hotel_id');
    }

     public function payments()
    {
        return $this->hasMany(PaymentHotels::class, 'invoices_id');
    }
}
