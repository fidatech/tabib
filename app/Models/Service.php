<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Illuminate\Database\Eloquent\Model;

/** A line of the price list: a dressing, an ECG, a blood test ... and what it costs. */
class Service extends Model
{
    use Audited;

    public const CATEGORIES = ['consultation' => 'Consultation', 'procedure' => 'Procedure', 'lab' => 'Laboratory', 'imaging' => 'Imaging', 'pharmacy' => 'Pharmacy', 'other' => 'Other'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'price' => 'decimal:2'];
    }

    public function label(): string
    {
        return (text_dir() === 'rtl' && $this->name_local) ? $this->name_local : $this->name;
    }
}
