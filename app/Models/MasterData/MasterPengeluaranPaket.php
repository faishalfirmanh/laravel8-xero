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

    // ✅ ACCESSOR AMAN
    public function getNamaPembuatAttribute()
    {
        return $this->creator?->name ?? '-';
    }
}
