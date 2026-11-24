<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A doctor of the clinic: a user who sees patients, with what the calendar and the papers need. */
class Doctor extends Model
{
    use Audited;

    /** The colours a doctor's appointments can have on the calendar: value => CSS class (written in full for the asset build). */
    public const COLOURS = [
        'teal' => 'appt-teal', 'blue' => 'appt-blue', 'violet' => 'appt-violet',
        'orange' => 'appt-orange', 'rose' => 'appt-rose', 'green' => 'appt-green',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'fee' => 'decimal:2'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function name(): string
    {
        return (string) $this->user?->name;
    }

    public function colourClass(): string
    {
        return self::COLOURS[$this->colour] ?? self::COLOURS['teal'];
    }

    public function auditLabel(): string
    {
        return $this->name();
    }

    /** The doctors who see patients now, by name. */
    public static function active()
    {
        return static::with('user')->where('is_active', true)->get()->sortBy(fn (Doctor $d) => $d->name())->values();
    }
}
