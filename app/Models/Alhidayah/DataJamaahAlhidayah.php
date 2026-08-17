<?php

namespace App\Models\Alhidayah;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DataJamaahAlhidayah extends Model
{
    use HasFactory;

    protected $connection = 'mysql_2';

    protected $table = 'data_jamaah';

    protected $fillable = [
        'id_jamaah',
        'agen',
        'no_ktp',//REQ
        'title',//REQ
        'age',
        'passport',
        'issued',
        'expired',
        'office',
        'tgl_lahir',//REQ
        'tempat_lahir',//REQ
        'imigrasi',
        'leader',
        'id_status',//
        'nama_jamaah',//REQ
        'alamat_jamaah',//REQ
        'tgl_vaksin_1',
        'tgl_vaksin_2',
        'no_tlp',//REQ
        'hp_jamaah',//REQ
        'foto',
        'kartukeluarga',
        'ktp',
        'surat_nikah',
        'keterangan',
        'transaksi',
        'user_id',
        'is_agen',
        'travel_agent',
        'location_prov',//REQ
        'location_city',//REQ
        'location_disct',//REQ
        'location_village',//REQ
    ];

    public $appends = [
        'full_address'
    ];

    public function getFullAddressAttribute()
    {
        $parts = [
            optional($this->prov)->name,
            optional($this->city)->name,
            optional($this->subdis)->name,
            optional($this->vill)->name,
            $this->alamat_jamaah
        ];

        // Menggabungkan array yang tidak kosong dengan koma
        return implode(', ', array_filter($parts));
    }

    public $hidden = [
        'agen',
        'place',
        'nama_di_vaksin',
        'jenis_vaksin',
        'jenis_vaksin_1',
        'jenis_vaksin_2',
        'jenis_vaksin_3',
        'jenis_vaksin_4',
        'tgl_vaksin_3',
        'tgl_vaksin_4',
        'imigrasi',
        'leader',
        'id_status',
        'age',
        'passport',
        'issued',
        'expired',
        'office',
        'tgl_vaksin_1',
        'tgl_vaksin_2',
        'foto',
        'kartukeluarga',
        'ktp',
        'surat_nikah',
        'keterangan',
        'transaksi',
        'user_id',
        'is_agen',
        'travel_agent',
    ];

    public function prov()
    {
        return $this->hasOne(AlhidProv::class, 'id', 'location_prov');
    }

    public function city()
    {
        return $this->hasOne(AlhidCity::class, 'id', 'location_city');
    }

    public function subdis()
    {
        return $this->hasOne(AlhidSub::class, 'id', 'location_disct');
    }

    public function vill()
    {
        return $this->hasOne(AlhidVill::class, 'id', 'location_village');
    }
}
