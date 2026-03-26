<?php

namespace App\Models\MasterData;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrackingCategory extends Model
{
    use HasFactory;

    protected $fillable = [
            'name_parent_category',
            'lines_category',
           'created_by'
    ];


  protected $casts = [
      'lines_category'     => 'array',
  ];



}
