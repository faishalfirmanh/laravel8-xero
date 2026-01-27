<?php

namespace App\Models\Revenue\Hotel;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Revenue\Hotel\InvoicesHotel;
class PaymentHotels extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoices_id',
        'payment_idr',
        'payment_sar',
        'created_by'
    ];

    public function getInvoice()
    {
        return $this->hasOne(InvoicesHotel::class, 'uuid_user_order','uuid_contact');
    }
}
