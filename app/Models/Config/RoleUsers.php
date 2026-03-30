<?php

namespace App\Models\Config;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RoleUsers extends Model
{
    use HasFactory;

     protected $fillable = [
        'role_id',//tabel master_role_users
        'user_id'
    ];
}
