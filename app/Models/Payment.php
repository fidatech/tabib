<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use App\Support\Num;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Money taken for an invoice; below zero, money given back (a refund). Never changed or deleted. */
class Payment extends Model
{
    use Audited;

    public const METHODS = ['cash' => 'Cash', 'card' => 'Card', 'bank' => 'Bank transfer', 'mobile' => 'Mobile money', 'other' => 'Other'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'received_at' => 'datetime'];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class)->withTrashed();
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function isRefund(): bool
    {
        return Num::cmp($this->amount, '0') < 0;
    }

    public function methodLabel(): string
    {
        return __(self::METHODS[$this->method] ?? $this->method);
    }

    public function auditLabel(): string
    {
        return $this->number;
    }
}
