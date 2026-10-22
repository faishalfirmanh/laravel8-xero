<?php

namespace App\Models\GoHalal;

use App\Models\DataJamaah;
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
        'id_jamaah_alhid'
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

    public function jamaahAlhid()
    {
        return $this->belongsTo(DataJamaah::class, 'id_jamaah_alhid', 'id_jamaah');
    }
}
