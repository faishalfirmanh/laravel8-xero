<?php

namespace App\Models;

use App\Models\MasterData\ItemDetailInvoices;
use App\Models\Transaction\TransactionNominalBankAccount;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoicesAllFromXero extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_number',
        'invoice_uuid',
        'invoice_amount',//invoice paid
        'invoice_total',//total yang harus di bayarkan
        'uuid_proudct_and_service',
        'issue_date',
        'due_date',
        'status',
        'uuid_contact',
        'contact_name',
        'item_name',
        'reference',
        'contact_id',//pengganti uuid_contact,masih tidak tau mau di join dengan tabel apa
        'less_nominal',//nominal kurang
        //0 ->draft,  1->awaiting payment (AUTHORISED),3,->paid ,4->void (VOIDED) = batal.
        //semua coa / account yang tercatat ketika approved / awaiting payment.
    ];


    public function getPayment()
    {
        return $this->hasMany(TransactionNominalBankAccount::class, 'id_parent_invoice');
    }

    public function getDetailById()
    {
        return $this->hasMany(ItemDetailInvoices::class, 'parent_inv_id', 'id');
    }

    public function getDetailByUUID()
    {
        return $this->hasMany(ItemDetailInvoices::class, 'invoice_uuid', 'invoice_uuid');
    }

}
