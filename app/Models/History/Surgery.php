<?php

namespace App\Models\History;

class Surgery extends Entry
{
    protected $table = 'patient_surgeries';

    protected function casts(): array
    {
        return [];
    }

    public function summary(): string
    {
        return $this->procedure.($this->performed ? ' · '.$this->performed : '');
    }
}
