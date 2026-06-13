<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SyncJobStatus extends Model
{
    use HasFactory;

    protected $fillable = [
        'job_id',
        'job_type',
        'status',
        'total_synced',
        'total_pages',
        'current_page',
        'error_message',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];
}
