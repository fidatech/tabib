<?php

namespace App\Models\History;

class Medication extends Entry
{
    protected $table = 'patient_medications';

    protected function casts(): array
    {
        return ['started_on' => 'date', 'stopped_on' => 'date'];
    }

    public function summary(): string
    {
        return trim($this->name.' '.$this->dose.' '.$this->frequency);
    }
}
