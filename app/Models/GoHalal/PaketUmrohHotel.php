<?php

namespace App\Models\GoHalal;

use App\Models\MasterData\Hotel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaketUmrohHotel extends Model
{
    use HasFactory;

    protected $table = 'paket_umroh_hotels';

    protected $fillable = [
        'paket_umroh_id',
        'hotel_id',
        'kota',
        'nama_hotel',
        'alamat',
        'jarak',
        'bintang',
        'night'
    ];

    protected $casts = [
        'jarak' => 'integer',
        'bintang' => 'integer',
    ];

    public function paket()
    {
        return $this->belongsTo(PaketUmroh::class, 'paket_umroh_id');
    }

    public function hotel()
    {
        return $this->belongsTo(Hotel::class, 'hotel_id');
    }
}
