<?php

namespace App\Models\GoHalal;

use App\Models\MasterData\TravelName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaketUmroh extends Model
{
    use HasFactory;

    protected $fillable = [
        'tipe',
        'travel_id',
        'nama_paket',
        'tgl_berangkat',
        'jumlah_hari',
        'maskapai',
        'keberangkatan',
        'landing',
        'kodeflight',
        'hrginfant',
        'hrgquad',
        'hrgtriple',
        'hrgdouble',
        'hrgperlengkapan',
        'imgpaketumroh',
        'itinerary',
        'max_slot',
        'available_slot',
        'filled_slot',
        'created_by',
        'random_uuid_paket',
        'price',
        'fasilitas_tambahan',
        'is_internal',// yang dikirim ke go halal go hanya yang status is_internal false & is_Posting true
        'is_posting',//
    ];

    protected $casts = [
        'tgl_berangkat' => 'date:Y-m-d',
        'jumlah_hari' => 'integer',
        'hrginfant' => 'float',
        'hrgquad' => 'float',
        'hrgtriple' => 'float',
        'hrgdouble' => 'float',
        'hrgperlengkapan' => 'float',
        'max_slot' => 'integer',
        'available_slot' => 'integer',
        'filled_slot' => 'integer',
    ];

    public function travel()
    {
        return $this->belongsTo(TravelName::class, 'travel_id');
    }

    public function jamaahs()
    {
        return $this->hasMany(PaketUmrohJamaah::class, 'paket_umroh_id');
    }
    public function hotels()
    {
        return $this->hasMany(PaketUmrohHotel::class, 'paket_umroh_id');
    }

    /**
     * Hitung ulang filled_slot & available_slot dari semua baris PaketUmrohJamaah
     * (jamaah internal + booking Halal Go).
     */
    public function syncSlot(): void
    {
        $filled = (int) $this->jamaahs()->sum('jumlah_jamaah');

        $this->update([
            'filled_slot' => $filled,
            'available_slot' => $this->max_slot - $filled,
        ]);
    }
}
