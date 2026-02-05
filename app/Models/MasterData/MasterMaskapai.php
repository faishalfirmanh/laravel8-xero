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

    // Relasi ke user
    public function creator() {
        return $this->belongsTo(User::class, 'created_by');
    }
}
