<?php

namespace App\Models\MasterData;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class MasterPengeluaranPaket extends Model
{
    use HasFactory;

    protected $fillable = [
        'nama_pengeluaran',
        'is_active',
        'created_by'
    ];

    protected $appends = [
        'nama_pembuat'
    ];

    // ✅ RELASI RESMI
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
