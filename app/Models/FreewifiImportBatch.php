<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FreewifiImportBatch extends Model
{
    protected $fillable = ['filename', 'type', 'imported_by', 'rows_total', 'rows_success',
        'rows_failed', 'error_log', 'job_status', 'started_at', 'completed_at'];

    protected $casts = [
        'error_log' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function importer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }
}
