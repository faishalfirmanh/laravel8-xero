<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConfigSettingXero extends Model
{
    //

    protected $casts = [
        'expires_at' => 'datetime',
    ];
    
    protected $fillable = [
        'access_token',
        'refresh_token',
        'xero_tenant_id',
        'barer_token',
        'id_token',
        'client_id',
        'client_secret',
        'code',
        'redirect_url',
        'expires_at'
    ];
}
