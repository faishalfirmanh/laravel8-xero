<?php

namespace App\Models\Expenses;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Expenses\PackageExpensesXero;
use App\Models\MasterData\MasterPengeluaranPaket;
class DPackageExpensesXero extends Model
{
    use HasFactory;

     protected $fillable =[
        'package_expenses_id',
        'pengeluaran_id',
        'nominal_idr',
        'nominal_sar',
        'is_idr',
        'combine_id_random',
        'nominal_currency'//config harus diisi jika , input adalah ryal
    ];

    protected $appends = [
        'nama_pengeluaran'
    ];

    public function getNamaPengeluaranAttribute()
    {
        return $this->getNameExpens->nama_pengeluaran;
    }

    public function getExpenPackage()
    {
        return $this->hasOne(PackageExpensesXero::class, 'id','package_expenses_id');
    }

    public function getNameExpens()
    {
        return $this->hasOne(MasterPengeluaranPaket::class, 'id','pengeluaran_id');
    }
}
