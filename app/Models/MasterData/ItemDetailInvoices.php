<?php

namespace App\Models\MasterData;

use App\Models\InvoicesAllFromXero;
use App\Models\ItemsPaketAllFromXero;
use App\Models\Transaction\TransactionAllCoa;
use App\Models\Transaction\TransactionNominalBankAccount;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ItemDetailInvoices extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_number',
        'uuid_invoices',
        'uuid_item',
        'qty',
        'unit_price',
        'total_amount_each_row',
        'line_item_uuid',
        'coa_id',//pake id,
        'parent_inv_id',
        'item_id',//kalau pake ini uuid_item ->dihapus
        'uuid_detail_inv',//Untuk transaction,
        'paket_tracking_uuid',
        'divisi_travel_tracking_uuid',
        'id_parent_inv',
        'desc',
    ];

    public $appends = [
        'tracking_category_paket',
        'tracking_category_divisi'
    ];

    public function getCoa()
    {
        return $this->hasOne(Coa::class, 'id', 'coa_id');
    }

    public function getParent()
    {
        return $this->belongsTo(InvoicesAllFromXero::class, 'parent_inv_id', 'id');
    }

    public function getItem()
    {
        return $this->belongsTo(ItemsPaketAllFromXero::class, 'item_id', 'id');
    }

    public function getTrackingCategoryPaketAttribute(): ?string
    {
        static $category = null; // cache dalam 1 request lifecycle

        if ($category === null) {
            $category = TrackingCategory::where('name_parent_category', 'nama paket')
                ->first();
        }

        if (!$category)
            return null;

        $matched = collect($category->lines_category)
            ->firstWhere('item_uuid_category', $this->paket_tracking_uuid);

        return $matched['item_name_category'] ?? null;
    }


    public function getTrackingCategoryDivisiAttribute(): ?string
    {
        static $category = null; // cache dalam 1 request lifecycle

        if ($category === null) {
            $category = TrackingCategory::where('name_parent_category', 'divisi')
                ->first();
        }

        if (!$category)
            return null;

        $matched = collect($category->lines_category)
            ->firstWhere('item_uuid_category', $this->divisi_travel_tracking_uuid);

        return $matched['item_name_category'] ?? null;
    }


    public function getTrans()
    {
        return $this->hasOne(TransactionAllCoa::class, 'uuid_detail_inv', 'uuid_detail');
    }

    public function getPayment()
    {
        return $this->hasMany(TransactionNominalBankAccount::class, 'id_parent_inv');
    }
}
