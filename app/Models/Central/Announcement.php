<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A message of Fida Technologies shown on top of the screens of every clinic, or of one. */
class Announcement extends Model
{
    use UsesCentralConnection;

    public const LEVELS = ['info' => 'Information', 'warning' => 'Warning', 'danger' => 'Urgent'];

    protected $guarded = [];

    protected $casts = ['starts_on' => 'date', 'ends_on' => 'date', 'is_active' => 'boolean'];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** What a clinic should see today. */
    public static function showing(Tenant $tenant, string $today): \Illuminate\Support\Collection
    {
        return static::where('is_active', true)
            ->where(fn ($q) => $q->whereNull('tenant_id')->orWhere('tenant_id', $tenant->id))
            ->where(fn ($q) => $q->whereNull('starts_on')->orWhereDate('starts_on', '<=', $today))
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $today))
            ->orderByDesc('id')->limit(5)->get();
    }
}
