<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A diagnosis of a visit, with its ICD-10 code when one was chosen. */
class EncounterDiagnosis extends Model
{
    protected $guarded = [];

    protected $casts = ['is_primary' => 'boolean'];

    public function label(): string
    {
        return $this->description.($this->icd10 ? ' ('.$this->icd10.')' : '');
    }
}
