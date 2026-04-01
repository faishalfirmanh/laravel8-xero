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


    public function role()
    {
        return $this->belongsTo(MasterRoleUser::class, 'role_id');
    }



}
