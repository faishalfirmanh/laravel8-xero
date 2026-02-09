<?php

namespace App\Models\MasterData;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class MasterMaskapai extends Model
{
    use HasFactory;

    protected $table = 'master_maskapais';

    protected $fillable = [
        'nama_maskapai',
        'created_by',
        'is_active',
    ];

    protected $appends = [
        'nama_pembuat'
    ];

    public function creator() {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function userCreate() {
        return $this->hasOne(User::class, 'id','created_by');
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
