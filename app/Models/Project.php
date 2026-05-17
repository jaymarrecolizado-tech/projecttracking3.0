<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
class Project extends Model
{
    protected static function booted(): void
    {
        static::deleting(function (Project $project) {
            $project->sites()->delete(); // Cascades soft-delete to sites
        });
    }
    protected $fillable = ['code','name','description','report_type',
                           'marker_color','marker_shape','marker_icon','logo_filename','is_active'];
    protected $casts = ['is_active' => 'boolean'];
    public function sites(): HasMany { return $this->hasMany(Site::class); }
    public function milestones(): HasMany { return $this->hasMany(ProjectMilestone::class)->orderBy('milestone_order'); }
    public function users(): BelongsToMany { return $this->belongsToMany(User::class, 'role_user'); }
    public function getOverallPctAttribute(): float {
        if ($this->report_type !== 'milestone') return 0;
        return SiteAccomplishment::whereHas('site', fn($q) => $q->where('project_id', $this->id))
            ->avg('pct_complete') ?? 0;
    }
}
