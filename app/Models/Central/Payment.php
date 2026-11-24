<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Money a clinic paid Fida Technologies, in US dollars. A wrong one is cancelled, never deleted. */
class Payment extends Model
{
    use UsesCentralConnection;

    public const METHODS = ['cash' => 'Cash', 'bank' => 'Bank transfer', 'hesabpay' => 'HesabPay', 'other' => 'Other'];

    protected $guarded = [];

    protected $casts = ['commission' => 'decimal:2', 'collected_by_seller' => 'boolean', 'amount' => 'decimal:2', 'paid_on' => 'date', 'cancelled_at' => 'datetime'];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** The reseller who earns the commission of this payment. */
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
