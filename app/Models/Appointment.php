<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A visit of a patient to a doctor. Its way through the day:
 *   booked → confirmed → arrived (gets a token in the queue) → with doctor → completed
 * or no-show / cancelled. A walk-in arrives at once.
 */
class Appointment extends Model
{
    use Audited;

    public const BOOKED = 'booked';

    public const CONFIRMED = 'confirmed';

    public const ARRIVED = 'arrived';

    public const WITH_DOCTOR = 'with_doctor';

    public const COMPLETED = 'completed';

    public const NO_SHOW = 'no_show';

    public const CANCELLED = 'cancelled';

    /** status => [label, badge class] (class names written in full for the asset build) */
    public const STATUSES = [
        self::BOOKED => ['Booked', 'bg-secondary'],
        self::CONFIRMED => ['Confirmed', 'bg-info'],
        self::ARRIVED => ['Waiting', 'bg-warning text-dark'],
        self::WITH_DOCTOR => ['With the doctor', 'bg-primary'],
        self::COMPLETED => ['Completed', 'bg-success'],
        self::NO_SHOW => ['Did not come', 'bg-danger'],
        self::CANCELLED => ['Cancelled', 'bg-danger'],
    ];

    /** What each action does: action => [from these statuses, to this status, button label]. */
    public const ACTIONS = [
        'confirm' => [[self::BOOKED], self::CONFIRMED, 'Confirm'],
        'arrive' => [[self::BOOKED, self::CONFIRMED], self::ARRIVED, 'Arrived'],
        'start' => [[self::ARRIVED], self::WITH_DOCTOR, 'Call in'],
        'complete' => [[self::ARRIVED, self::WITH_DOCTOR], self::COMPLETED, 'Done'],
        'no_show' => [[self::BOOKED, self::CONFIRMED], self::NO_SHOW, 'Did not come'],
        'cancel' => [[self::BOOKED, self::CONFIRMED, self::ARRIVED], self::CANCELLED, 'Cancel'],
    ];

    /** These still hold their time on the doctor's calendar. */
    public const HOLDING = [self::BOOKED, self::CONFIRMED, self::ARRIVED, self::WITH_DOCTOR, self::COMPLETED];

    public const SOURCES = ['desk' => 'Front desk', 'walk_in' => 'Walk-in', 'online' => 'Online'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'arrived_at' => 'datetime', 'started_at' => 'datetime', 'finished_at' => 'datetime', 'token' => 'integer'];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class)->withTrashed();
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(AppointmentType::class, 'appointment_type_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function statusLabel(): string
    {
        return __(self::STATUSES[$this->status][0] ?? $this->status);
    }

    public function statusClass(): string
    {
        return self::STATUSES[$this->status][1] ?? 'bg-secondary';
    }

    /** The actions that can be taken on it now. */
    public function actions(): array
    {
        return array_keys(array_filter(self::ACTIONS, fn (array $a) => in_array($this->status, $a[0], true)));
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [self::BOOKED, self::CONFIRMED], true);
    }

    public function auditLabel(): string
    {
        return $this->starts_at?->format('Y-m-d H:i').' '.$this->patient?->name;
    }
}
