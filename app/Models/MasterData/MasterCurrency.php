<?php

namespace App\Models\MasterData;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class MasterCurrency extends Model
{
    use HasFactory;

    protected $table = 'm_currency';

    protected $fillable = [
        'code_curr',
        'satu_rupiah',
        'nominal_currency',
    ];

    protected $appends = [
        'nama_pembuat'
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function userCreate()
    {
        return $this->hasOne(User::class, 'id', 'created_by');
    }

    public function getNamaPembuatAttribute()
    {
        if ($this->creator) {

            if ($this->creator->name) {

                return $this->creator->name;
            } else {
                return 'nama kosong';
            }
        } else {
            return '-';
        }
    }
}
