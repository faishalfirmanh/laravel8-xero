<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DataJamaah extends Model
{
   //

   protected $connection = 'mysql_2';
   protected $table = 'data_jamaah';
   public $timestamps = false;


   protected $fillable = [
      "is_updated_to_xero",
      "agen",
      "no_ktp",
      "title",
      "age",
      "place",
      "passport",
      "issued",
      "expired",
      "office",
      "tgl_lahir",
      "tempat_lahir",
      "estimasi_berangkat",
      "imigrasi",
      "leader",
      "id_status",
      "nama_jamaah",
      "nama_tambahan",
      "alamat_jamaah",
      "nama_di_vaksin",
      "jenis_vaksin",
      "tgl_vaksin_1",
      "tgl_vaksin_2",
      "no_tlp",
      "hp_jamaah",
      "foto",
      "kartukeluarga",
      "ktp",
      "surat_nikah",
      "keterangan",
      "transaksi",
      "jenis_vaksin_2",
      "jenis_vaksin_3",
      "jenis_vaksin_4",
      "tgl_vaksin_3",
      "tgl_vaksin_4",
      "user_id",
      "created_at",
      "updated_at",
      "is_agen",
      "travel_agent",
      "is_updated_to_xero",
      "random_uuid",
      "is_updated",
      "location_prov",
      "location_city",
      "location_disct",
      "location_village",
      "status_generate",
      "child_id_single_link",
   ];


   protected $appends = [
      'full_address'
   ];

   public function getFullAddressAttribute()
   {
      $prov = LocProv::where('id', $this->location_prov)->value('name') ?? '';
      $city = LocCity::where('id', $this->location_city)->value('name') ?? '';
      $dist = LocSubdis::where('id', $this->location_disct)->value('name') ?? '';
      $villa = LocVill::where('id', $this->location_village)->value('name') ?? '';

      return implode(', ', array_filter([
         $prov,
         $city,
         $dist,
         $villa,
      ]));

   }



}
