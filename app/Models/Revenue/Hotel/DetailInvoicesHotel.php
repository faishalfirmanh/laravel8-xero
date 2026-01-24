<?php

namespace App\Models\Revenue\Hotel;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Revenue\Hotel\InvoicesHotel;
class DetailInvoicesHotel extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'invoices_hotels',
        'cascade',
        'type_room',
        'qty',
        'price_each_item',
        'total_amount',
        'desc'
    ];


    protected $appends = [
        'type_room_desc'
    ];

    public function getTypeRoomDescAttribute()
    {
        $cek = $this->type_room;
        if($cek == 9){
            return 'bed';
        }else if($cek == 8){
            return 'room only';
        }else if($cek == 5){
            return 'quit';
        }else if($cek == 3){
            return 'triple';
        }else if($cek == 2){
            return 'double';
        }else if($cek == 4){
            return 'quad';
        }else{
            return '-';
        }
    }

    public function invoice()
    {
        return $this->belongsTo(InvoicesHotel::class, 'invoice_id');
    }
}
