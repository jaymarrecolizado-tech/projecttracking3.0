<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteAccomplishment extends Model
{
    protected $fillable = ['site_id', 'milestone_id', 'status', 'pct_complete',
        'target_date', 'actual_date', 'remarks', 'attachment_path', 'created_by'];

    protected $casts = [
        'target_date' => 'date',
        'actual_date' => 'date',
        'pct_complete' => 'decimal:2',
    ];

    /** @return BelongsTo<Site, $this> */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /** @return BelongsTo<ProjectMilestone, $this> */
    public function milestone(): BelongsTo
    {
        return $this->belongsTo(ProjectMilestone::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
