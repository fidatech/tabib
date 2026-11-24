<?php

namespace App\Models;

use App\Models\Concerns\HasRevisions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One set of vital signs, taken by a nurse or a doctor. Marked "entered in error", never deleted. */
class Vital extends Model
{
    use HasRevisions;

    /** measure => [label, unit, lowest believable, highest believable] */
    public const MEASURES = [
        'systolic' => ['Systolic BP', 'mmHg', 50, 260],
        'diastolic' => ['Diastolic BP', 'mmHg', 30, 160],
        'pulse' => ['Pulse', '/min', 20, 250],
        'temperature' => ['Temperature', '°C', 30, 44],
        'respiration' => ['Respiration', '/min', 4, 80],
        'spo2' => ['SpO₂', '%', 50, 100],
        'weight' => ['Weight', 'kg', 0.5, 350],
        'height' => ['Height', 'cm', 30, 250],
        'blood_sugar' => ['Blood sugar', 'mg/dL', 20, 800],
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['taken_at' => 'datetime', 'temperature' => 'float', 'weight' => 'float', 'height' => 'float'];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeKept($query)
    {
        return $query->where('status', '!=', 'entered_in_error');
    }

    /** Body mass index, when the weight and the height are known. */
    public function bmi(): ?float
    {
        return $this->weight && $this->height ? round($this->weight / (($this->height / 100) ** 2), 1) : null;
    }

    public function bloodPressure(): ?string
    {
        return $this->systolic && $this->diastolic ? $this->systolic.'/'.$this->diastolic : null;
    }

    /** "BP 120/80 · Pulse 72 · Temp 37.2 °C ..." */
    public function summary(): string
    {
        $parts = [];
        if ($bp = $this->bloodPressure()) {
            $parts[] = __('BP').' '.$bp;
        }
        foreach (['pulse', 'temperature', 'respiration', 'spo2', 'weight', 'height', 'blood_sugar'] as $m) {
            if ($this->{$m} !== null) {
                $parts[] = __(self::MEASURES[$m][0]).' '.(float) $this->{$m}.' '.self::MEASURES[$m][1];
            }
        }
        if ($bmi = $this->bmi()) {
            $parts[] = __('BMI').' '.$bmi;
        }

        return implode(' · ', $parts);
    }
}
