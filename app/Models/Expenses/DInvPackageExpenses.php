<?php

namespace App\Models\Expenses;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Expenses\PackageExpensesXero;
use App\Models\InvoicesAllFromXero;
class DInvPackageExpenses extends Model
{
    use HasFactory;


   protected $fillable = [
        'package_expenses_id',
        'invoices_xero_id',
        'amount_invoice'
    ];

    protected $appends = [
        'inv_number'
    ];


    public function getInvNumberattribute()
    {
        return $this->getInvoiceAllXero->invoice_number;
        //return $this->hasOne(PackageExpensesXero::class, 'id','package_expenses_id');
    }



     public function getExpenPackage()
    {
        return $this->hasOne(PackageExpensesXero::class, 'id','package_expenses_id');
    }

    public function getInvoiceAllXero()
    {
        return $this->hasOne(InvoicesAllFromXero::class, 'id','invoices_xero_id');
    }
}
