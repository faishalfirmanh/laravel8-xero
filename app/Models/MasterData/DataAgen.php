<?php

namespace App\Models\MasterData;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DataAgen extends Model
{
    use HasFactory;

    protected $table = 'data_jamaah';

    protected $fillable = [
        'nama_lengkap', 'no_ktp', 'no_hp',
        'id_prov', 'id_kab', 'id_kec',
        'detail_alamat', 'tempat_lahir', 'tanggal_lahir'
    ];
}
