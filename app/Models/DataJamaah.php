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
      "is_updated_to_xero"
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
