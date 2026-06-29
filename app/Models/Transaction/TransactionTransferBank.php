<?php

namespace App\Models\Transaction;

use App\Models\MasterData\BankXero;
use App\Models\MasterData\TrackingCategory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransactionTransferBank extends Model
{
    use HasFactory;



    protected $fillable = [
        'bank_id_from',
        'bank_id_to',
        'date_trans',
        'amount',
        'reference_transfer_bank',
        'code_tracking_paket_from',
        'code_tracking_divisi_from',
        'code_tracking_paket_to',
        'code_tracking_divisi_to',
    ];



    protected $appends = [
        'bank_name_from',
        'bank_name_to',
        'tracking_category_paket_from',
        'tracking_category_divisi_from',
        'tracking_category_paket_to',
        'tracking_category_divisi_to'
    ];


    public function getBankNameFromAttribute()
    {
        return optional($this->getBankFrom)->name ?? 'no name';
    }

    public function getBankNameToAttribute()
    {
        return optional($this->getBankTo)->name ?? 'no name';
    }



    public function getTrackingCategoryPaketFromAttribute(): ?string
    {
        static $category = null; // cache dalam 1 request lifecycle

        if ($category === null) {
            $category = TrackingCategory::where('name_parent_category', 'nama paket')
                ->first();
        }

        if (!$category)
            return null;

        $matched = collect($category->lines_category)
            ->firstWhere('item_uuid_category', $this->code_tracking_paket_from);

        return $matched['item_name_category'] ?? null;
    }


    public function getTrackingCategoryDivisiFromAttribute(): ?string
    {
        static $category = null; // cache dalam 1 request lifecycle

        if ($category === null) {
            $category = TrackingCategory::where('name_parent_category', 'divisi')
                ->first();
        }

        if (!$category)
            return null;

        $matched = collect($category->lines_category)
            ->firstWhere('item_uuid_category', $this->code_tracking_divisi_from);

        return $matched['item_name_category'] ?? null;
    }



    public function getTrackingCategoryPaketToAttribute(): ?string
    {
        static $category = null;

        if ($category === null) {
            $category = TrackingCategory::where('name_parent_category', 'nama paket')
                ->first();
        }

        if (!$category)
            return null;

        $matched = collect($category->lines_category)
            ->firstWhere('item_uuid_category', $this->code_tracking_paket_to);

        return $matched['item_name_category'] ?? null;
    }


    public function getTrackingCategoryDivisiToAttribute(): ?string
    {
        static $category = null; // cache dalam 1 request lifecycle

        if ($category === null) {
            $category = TrackingCategory::where('name_parent_category', 'divisi')
                ->first();
        }

        if (!$category)
            return null;

        $matched = collect($category->lines_category)
            ->firstWhere('item_uuid_category', $this->code_tracking_divisi_to);

        return $matched['item_name_category'] ?? null;
    }



    public function getBank()
    {
        return $this->hasOne(BankXero::class, 'id', 'bank_id');
    }

    public function getBankFrom()
    {
        return $this->hasOne(BankXero::class, 'id', 'bank_id_from');
    }

    public function getBankTo()
    {
        return $this->hasOne(BankXero::class, 'id', 'bank_id_to');
    }
}
