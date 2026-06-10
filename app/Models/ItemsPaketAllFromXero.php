<?php

namespace App\Models;

use App\Models\MasterData\Coa;
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
        'jenis_item',  //#1 = paket umroh,
        // #2 = paket haji,
        // #3 = perlengkapan haji,terkait haji, ex : visa, passport, guide, bis
        //  #4 = perlengkapan umroh,terkait umroh, ex : visa, passport, guide,bis
        // #5 = operasional kantor,
        //#6 = lain-lain

        'price_purchase',
        'price_sales',
        'desc',
        'account_id_purchase',//untuk coa id local kalau insert dari web, tidak sync dari xero, relasi dengan coas->id
        'account_id_salles',//untuk coa id local kalau insert dari web, tidak sync dari xero,
        'desc_salles',
        'tax_rate_salles',//0->salles tax on imports, 1 tax exempt, 2 tax on purchase, 3 tax on salles
        'tax_rate_purchase'
    ];


    public function getCoaSalles()
    {
        return $this->hasOne(Coa::class, 'id', 'account_id_salles');
    }

    public function getCoaPurchase()
    {
        return $this->hasOne(Coa::class, 'id', 'account_id_purchase');
    }
}
