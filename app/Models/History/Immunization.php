<?php

namespace App\Models\History;

class Immunization extends Entry
{
    protected $table = 'patient_immunizations';

    protected function casts(): array
    {
        return ['given_on' => 'date'];
    }

    public function summary(): string
    {
        return $this->vaccine.($this->dose_no ? ' · '.__('dose').' '.$this->dose_no : '');
    }
}
