<?php

namespace App\Models\History;

class Problem extends Entry
{
    protected $table = 'patient_problems';

    protected function casts(): array
    {
        return ['onset_on' => 'date', 'resolved_on' => 'date'];
    }

    public function summary(): string
    {
        return $this->description.($this->icd10 ? ' ('.$this->icd10.')' : '');
    }
}
