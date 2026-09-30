<?php

namespace App\Models\MasterData\Toko;

use App\Models\ItemsPaketAllFromXero;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductUom extends Model
{
    use HasFactory;

    protected $fillable = [
        'item_id',
        'unit_satuan_id',
        'conversion_factor',
        'sell_price',
    ];

    protected $casts = [
        'conversion_factor' => 'decimal:4',
        'sell_price' => 'decimal:4',
    ];

    public function item()
    {
        return $this->belongsTo(ItemsPaketAllFromXero::class, 'item_id');
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class, 'unit_satuan_id');
    }
}
