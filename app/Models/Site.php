<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
class Site extends Model
{
    use SoftDeletes;
    
    protected static function booted(): void
    {
        static::deleting(function (Site $site) {
            $site->dailyStatuses()->delete();
            $site->accomplishments()->delete();
        });
    }

    protected $fillable = ['project_id','nationwide_id','ap_site_code','location_name',
                           'ap_site_name','site_type','barangay','municipality','province',
                           'district','region','island_group','latitude','longitude',
                           'date_of_activation','status','isp_provider','last_mile_tech',
                           'bw_download_cir','metadata','created_by','updated_by'];
    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'date_of_activation' => 'date',
        'metadata' => 'array',
    ];
    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function dailyStatuses(): HasMany { return $this->hasMany(SiteDailyStatus::class); }
    public function accomplishments(): HasMany { return $this->hasMany(SiteAccomplishment::class); }
    public function latestDailyStatus(): HasOne { return $this->hasOne(SiteDailyStatus::class)->latestOfMany('date'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function updater(): BelongsTo { return $this->belongsTo(User::class, 'updated_by'); }
}
