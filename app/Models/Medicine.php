<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Illuminate\Database\Eloquent\Model;

/** A medicine the clinic prescribes: from the shipped list (database/data/medicines.csv) or added by the clinic. */
class Medicine extends Model
{
    use Audited;

    public const FORMS = ['tablet', 'capsule', 'syrup', 'suspension', 'drops', 'injection', 'infusion', 'cream', 'ointment', 'gel', 'lotion',
        'eye drops', 'ear drops', 'nasal spray', 'inhaler', 'nebuliser solution', 'suppository', 'sachet', 'vaginal tablet', 'shampoo', 'other'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['controlled' => 'boolean', 'is_custom' => 'boolean', 'is_active' => 'boolean', 'mg_per_unit' => 'float',
            'child_max_mg_per_kg_dose' => 'float', 'max_daily_mg' => 'float'];
    }

    /** "Amoxicillin 500 mg capsule" */
    public function label(): string
    {
        return trim($this->name.' '.$this->strength.' '.__($this->form));
    }

    public function auditLabel(): string
    {
        return $this->label();
    }

    /** By generic name, brand or class: every word must match. */
    public function scopeSearch($query, string $term)
    {
        foreach (preg_split('/\s+/u', trim($term)) as $word) {
            if ($word === '') {
                continue;
            }
            $like = '%'.$word.'%';
            $query->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('brands', 'like', $like)->orWhere('strength', 'like', $like)->orWhere('form', 'like', $like));
        }

        return $query;
    }
}
