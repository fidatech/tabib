<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Money a reseller reports he took from a client. It becomes a payment when the administrator confirms it. */
class SellerClaim extends Model
{
    use UsesCentralConnection;

    public const STATUSES = ['pending' => 'Waiting for confirmation', 'confirmed' => 'Confirmed', 'rejected' => 'Rejected'];

    public const TONES = ['pending' => 'warning', 'confirmed' => 'success', 'rejected' => 'danger'];

    protected $guarded = [];

    protected $casts = ['amount' => 'decimal:2', 'paid_on' => 'date', 'decided_at' => 'datetime'];

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
