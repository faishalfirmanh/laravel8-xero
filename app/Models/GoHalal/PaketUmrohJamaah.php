<?php

namespace App\Models\GoHalal;

use App\Models\MasterData\DataJamaahXero;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaketUmrohJamaah extends Model
{
    use HasFactory;

    protected $fillable = [
        'paket_umroh_id',
        'data_jamaah_id',
        'jumlah_jamaah',
        'created_by',
    ];

    protected $casts = [
        'jumlah_jamaah' => 'integer',
    ];

    public function paket()
    {
        return $this->belongsTo(PaketUmroh::class, 'paket_umroh_id');
    }

    public function jamaah()
    {
        return $this->belongsTo(DataJamaahXero::class, 'data_jamaah_id');
    }
}
