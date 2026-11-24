<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Money between a reseller and us. A wrong one is cancelled, never deleted. */
class SellerSettlement extends Model
{
    use UsesCentralConnection;

    /** in: the reseller hands over what he took from clients. out: we pay him commission. */
    public const DIRECTIONS = ['in' => 'The reseller paid us', 'out' => 'We paid the reseller'];

    protected $guarded = [];

    protected $casts = ['amount' => 'decimal:2', 'paid_on' => 'date', 'cancelled_at' => 'datetime'];

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }
}
