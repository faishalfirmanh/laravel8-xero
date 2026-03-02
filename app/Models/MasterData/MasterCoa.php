<?php

namespace App\Models\MasterData;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterCoa extends Model
{
    use HasFactory;
    protected $table = 'master_coa';

    protected $fillable = [
        'xero_account_id',
        'code',
        'name',
        'description',
        'type',
        'tax_type',
        'tax_rate',
        'ytd'
        
    ];
}
