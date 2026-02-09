<?php

namespace App\Models\MasterData;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class MasterRoleUser extends Model
{
    use HasFactory;

    protected $table = 'master_role_users';

    protected $fillable = [
        'nama_role',
        'is_active',
        'created_by'
    ];

    protected $appends = ['nama_pembuat'];

    // RELASI
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
    return 'nama kosong';}
    }
    else{
    return '-';}
    }
    }
