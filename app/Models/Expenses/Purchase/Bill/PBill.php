<?php

namespace App\Models\Expenses\Purchase\Bill;

use App\Models\MasterData\DataJamaahXero;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PBill extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid_from',//id dari tabel jamaah
        'date_req',
        'due_date',
        'reference',
        'amounts_are',
        'subtotal',
        'total',
        'tax',
        'nominal_paid',
        'nominal_due',
        'status',//0=draft,1=awaiting,  2=paid,
        'currency'
    ];

    public function getContactFrom()
    {
        return $this->belongsTo(DataJamaahXero::class, 'contact_uuid', 'uuid_from');
    }

}
