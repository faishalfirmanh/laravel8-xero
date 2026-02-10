<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogHistory extends Model
{
    protected $table = 'log_histories';

    protected $fillable = [
        'user_id',
        'ip_address',
        'browser',
        'action',
        'created_by',
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
