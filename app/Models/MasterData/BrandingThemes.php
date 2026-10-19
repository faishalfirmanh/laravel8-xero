<?php

namespace App\Models\MasterData;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BrandingThemes extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'company_name',
        'travel_id',
        'bank_accounts',
        'address',
        'phone',
        'email',
        'logo_path',
        'primary_color',
        'terms',
        'signature_name',
        'signature_title',
        'is_default',
        'created_by',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function travel()
    {
        return $this->belongsTo(TravelName::class, 'travel_id');
    }
    public static function defaultId()
    {
        return static::where('is_default', true)->value('id');
    }
}
