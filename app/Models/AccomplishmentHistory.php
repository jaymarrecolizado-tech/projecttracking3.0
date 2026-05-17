<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class AccomplishmentHistory extends Model
{
    public $timestamps = false;
    protected $fillable = ['accomplishment_id','old_status','new_status','old_pct','new_pct','changed_by','changed_at'];
    protected $casts = ['changed_at' => 'datetime'];
    public function accomplishment(): BelongsTo { return $this->belongsTo(SiteAccomplishment::class); }
    public function changer(): BelongsTo { return $this->belongsTo(User::class, 'changed_by'); }
}
