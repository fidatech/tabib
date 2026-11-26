<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Money the clinic spent. Never changed or deleted: a wrong one is voided with a reason. */
class Expense extends Model
{
    use Audited;

    public const CATEGORIES = [
        'rent' => 'Rent', 'salaries' => 'Salaries', 'commission' => 'Doctor commission', 'medicines' => 'Medicines and supplies', 'lab' => 'Laboratory',
        'utilities' => 'Electricity, water, internet', 'equipment' => 'Equipment and repairs', 'cleaning' => 'Cleaning', 'tax' => 'Taxes and fees', 'other' => 'Other',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['spent_on' => 'date', 'amount' => 'decimal:2', 'voided_at' => 'datetime'];
    }

    public function scopeCounted($query)
    {
        return $query->whereNull('voided_at');
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function categoryLabel(): string
    {
        return __(self::CATEGORIES[$this->category] ?? $this->category);
    }

    public function auditLabel(): string
    {
        return $this->number;
    }
}
