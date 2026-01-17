<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ItemsPaketAllFromXero extends Model
{
    use HasFactory;

    //kalau di xero : products-and-services
    protected $fillable = [
        'uuid_proudct_and_service',
        'code',
        'nama_paket',
        'purchase_AccountCode',
        'sales_AccountCode',
        'total_hari',
        'jenis_item',  //1 = paket umroh,
                        // 2 = paket haji,
                        // 3 = perlengkapan haji,terkait haji, ex : visa, passport, guide, bis
                        //  4 = perlengkapan umroh,terkait umroh, ex : visa, passport, guide,bis
                        // 5 = operasional kantor,
                        //6 = lain-lain

        'price_purchase',
        'price_sales',
        'desc'
    ];
}
