<?php

namespace App\Models\Revenue\Hotel;

use App\Http\Repository\MasterData\Finance\InvoiceAllXeroRepo;
use App\Models\InvoicesAllFromXero;
use App\Models\MasterData\DataJamaahXero;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Revenue\Hotel\PaymentHotels;
use App\Models\Revenue\Hotel\DetailInvoicesHotel;
use App\Models\MasterData\Hotel;
use App\Models\User;

class InvoicesHotel extends Model
{
    use HasFactory;

    //tabel bayangan invoice real di simpan di tabel invoices_all_from_xeros
    protected $appends = [
        'hotel_name',
        'name_created_user',
        'contact_xero_name',
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
            'less_payment_sar',
            'contact_id_x',//data jamaah xero
            'contact_id_alhid',//data jamaah alhid
            'nominal_currency_rp',
            'type_currency'
        ];

    public function getStatusNamePaymentAttribute()
    {
        if ($this->status == 1) {
            return 'Belum Dibayar';
        } else if ($this->status == 2) {
            return 'Proses Pembayaran';
        } else {
            return 'Lunas';
        }

    }

    public function getNameCreatedUserAttribute()
    {
        if (isset($this->created_by)) {
            $data = User::find($this->created_by);
            return $data->name;
        } else {
            return '-';
        }

    }

    public function contactXero()
    {
        return $this->hasMany(DataJamaahXero::class, 'contact_id_x');
    }

    public function getContactXeroNameAttribute()
    {
        $data = DataJamaahXero::find($this->contact_id_x);
        return $data ? $data->full_name : '-';
    }

    public function getHotelNameAttribute()
    {
        $data = Hotel::find($this->hotel_id);
        return $data->name;
    }


    public function getInvXero()
    {
        return $this->hasOne(InvoicesAllFromXero::class, 'invoice_number', 'no_invoice_hotel');
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
