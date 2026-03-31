<?php

namespace App\Models\MasterData;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
class TravelName extends Model
{
    use HasFactory;

      protected $appends = ['nama_pembuat'];

    protected $fillable = [
        'name',
        'created_by',
        'is_active'
    ];


     public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }


     public function getNamaPembuatAttribute()
    {
        if($this->creator){

            if($this->creator->name){
                return $this->creator->name;
            }
            else{
                return 'nama kosong';
            }
        }
        else{
            return '-';
        }
    }
}
