<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectMilestone extends Model
{
    protected $fillable = ['project_id', 'milestone_name', 'milestone_order', 'weight_pct', 'description'];

    protected $casts = ['weight_pct' => 'decimal:2'];

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return HasMany<SiteAccomplishment, $this> */
    public function accomplishments(): HasMany
    {
        return $this->hasMany(SiteAccomplishment::class);
    }
}
