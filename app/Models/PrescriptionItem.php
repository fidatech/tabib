<?php

namespace App\Models;

use App\Services\Prescribing;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One medicine of a prescription, with how it is taken. */
class PrescriptionItem extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['dose_amount' => 'float', 'days' => 'integer'];
    }

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }

    public function label(): string
    {
        return trim($this->name.' '.$this->strength.' '.($this->form ? __($this->form) : ''));
    }

    /** How it is taken, in words, in the language asked for (en, fa, ps). */
    public function directions(?string $locale = null): string
    {
        return Prescribing::directions($this, $locale);
    }

    /** The milligrams of one dose, when they can be known. */
    public function doseMg(): ?float
    {
        if ($this->dose_amount === null) {
            return null;
        }
        if ($this->dose_unit === 'mg') {
            return $this->dose_amount;
        }
        if ($this->dose_unit === 'g') {
            return $this->dose_amount * 1000;
        }
        $perUnit = $this->medicine?->mg_per_unit;
        if ($perUnit && in_array($this->dose_unit, ['tablet', 'capsule', 'ml', 'sachet', 'suppository'], true)) {
            return $this->dose_amount * $perUnit;
        }

        return null;
    }
}
