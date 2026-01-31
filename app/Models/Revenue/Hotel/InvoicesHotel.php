<?php

namespace App\Models\Revenue\Hotel;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Revenue\Hotel\PaymentHotels;
use App\Models\Revenue\Hotel\DetailInvoicesHotel;
use App\Models\MasterData\Hotel;
use App\Models\User;

class InvoicesHotel extends Model
{
    use HasFactory;

    protected $appends = [
     'hotel_name',
     'name_created_user',
     'status_name_payment'
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
        'status',//1 belum dibayar, //2 proses pembayaran //3 lunas
        'final_payment_sar',
        'final_payment_idr',
        'less_payment_idr',
        'less_payment_sar'
    ];

     public function getStatusNamePaymentAttribute()
    {
        if($this->status == 1){
             return 'Belum Dibayar';
        }else if($this->status == 2){
            return 'Proses Pembayaran';
        }else{
            return 'Lunas';
        }

    }

    public function getNameCreatedUserAttribute()
    {
        if(isset($this->created_by)){
             $data = User::find($this->created_by);
             return $data->name;
        }else{
            return '-';
        }

    }

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
