<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;

/** One clinic (a customer of Fida Technologies) with its address, database and software licence. */
class Tenant extends Model
{
    use UsesCentralConnection;

    public const ACTIVE = 'active';

    /** Registered on the website and not approved yet: its people sign in, but only the subscription page opens. */
    public const PENDING = 'pending';

    public const SUSPENDED = 'suspended';

    public const ARCHIVED = 'archived';

    public const STATUSES = [self::ACTIVE => 'Active', self::PENDING => 'Waiting for approval', self::SUSPENDED => 'Suspended', self::ARCHIVED => 'Archived'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'main_branch_price' => 'decimal:2',
            'branch_price' => 'decimal:2',
            'max_branches' => 'integer',
            'max_doctors' => 'integer',
            'max_sms_month' => 'integer',
            'licence_expires_on' => 'date',
        ];
    }

    public function invoices(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** Days until the licence ends: negative when it has ended, null when there is no date. */
    public function daysLeft(?string $today = null): ?int
    {
        if (! $this->licence_expires_on) {
            return null;
        }

        return (int) \Illuminate\Support\Carbon::parse($today ?: now()->toDateString())->startOfDay()->diffInDays($this->licence_expires_on->copy()->startOfDay(), false);
    }

    /** The reseller who sold it, if one did. */
    public function seller(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }

    public function isActive(): bool
    {
        return $this->status === self::ACTIVE;
    }
}
