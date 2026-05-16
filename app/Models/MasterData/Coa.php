<?php

namespace App\Models\MasterData;

use App\Models\Transaction\TransactionAllCoa;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Coa extends Model
{
    use HasFactory;


    protected $fillable = [
        'code',
        'account_type',//string tidak bebas
        'name',
        'created_by',
        'desc',
        'account_uuid',
        'is_active',
        'currency_code'
    ];

    protected $appends = [
        'sum_nominal'
    ];

    public function getTrans()
    {
        return $this->hasMany(TransactionAllCoa::class, 'id', 'uuid_coa');
    }

    public function getSumNominalAttribute()
    {
        return TransactionAllCoa::where('uuid_coa', $this->id)->sum('nominal');
    }
}
