<?php

namespace App\Models\Config;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

use App\Models\MasterData\TravelName;
class TravelUser extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'travel_id'
    ];

    protected $appends = [
        'nama_travel'
    ];

    public function getNamaTravelAttribute()
    {
        return $this->travel ?? '-';
    }


  public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function travel()
    {
        return $this->belongsTo(TravelName::class,'travel_id');
    }


}
