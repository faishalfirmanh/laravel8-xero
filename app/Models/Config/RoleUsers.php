<?php

namespace App\Models\Config;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\MasterData\MasterRoleUser;
class RoleUsers extends Model
{
    use HasFactory;

     protected $fillable = [
        'role_id',//tabel master_role_users
        'user_id'
     ];

    // protected $appends = [
    //     'lini_usaha'
    // ];

    public function role()
    {
        return $this->belongsTo(MasterRoleUser::class, 'role_id');
    }


    // public function getLiniUsahaAttribute()
    // {
    //     // if ($this->relationLoaded('role') && $this->role) {
        //     return $this->role->lini_usaha ?? '-';   // pakai accessor dari MasterRoleUser
        // }

    //     return $this->role;
    // }



}
