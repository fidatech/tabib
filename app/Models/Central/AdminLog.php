<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** What a system administrator did in the central panel. */
class AdminLog extends Model
{
    use UsesCentralConnection;

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected $casts = ['created_at' => 'datetime'];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    public static function record(string $action, ?Tenant $tenant = null, ?string $description = null): void
    {
        try {
            static::create([
                'admin_id' => auth('admin')->id(),
                'tenant_id' => $tenant?->getKey(),
                'action' => $action,
                'description' => mb_substr((string) ($description ?? $tenant?->name), 0, 500),
                'ip' => request()?->ip(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
