<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Illuminate\Database\Eloquent\Model;

/** A kind of appointment: a first visit, a follow-up, a dressing ... with how long it takes and what it costs. */
class AppointmentType extends Model
{
    use Audited;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'price' => 'decimal:2', 'minutes' => 'integer'];
    }

    /** The name in the language of the screen. */
    public function label(): string
    {
        return (text_dir() === 'rtl' && $this->name_local) ? $this->name_local : $this->name;
    }
}
