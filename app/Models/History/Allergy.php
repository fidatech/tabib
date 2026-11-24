<?php

namespace App\Models\History;

class Allergy extends Entry
{
    protected $table = 'patient_allergies';

    protected function casts(): array
    {
        return [];
    }

    public function summary(): string
    {
        return $this->substance.($this->reaction ? ' — '.$this->reaction : '').($this->severity ? ' ('.__(ucfirst($this->severity)).')' : '');
    }
}
