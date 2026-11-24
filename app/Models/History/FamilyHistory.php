<?php

namespace App\Models\History;

class FamilyHistory extends Entry
{
    protected $table = 'patient_family_history';

    protected function casts(): array
    {
        return [];
    }

    public function summary(): string
    {
        return __(ucfirst($this->relative)).': '.$this->condition;
    }
}
