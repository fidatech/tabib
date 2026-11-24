<?php

namespace App\Services;

use App\Exceptions\UserError;
use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\Doctor;
use App\Models\Patient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The rules of the calendar and the queue, in one place:
 *   - a doctor never has two booked appointments at the same time;
 *   - a patient who arrives gets the next token of the day in his branch;
 *   - an appointment moves only along the steps of Appointment::ACTIONS.
 */
class Scheduling
{
    /** Book a patient with a doctor. $start is the date and time in the clinic's time zone. */
    public static function book(Patient $patient, Doctor $doctor, ?AppointmentType $type, Carbon $start, ?int $minutes = null, ?string $reason = null, string $source = 'desk'): Appointment
    {
        $minutes = $minutes ?: ($type?->minutes ?: (int) setting('slot_minutes', '15'));
        $end = $start->copy()->addMinutes($minutes);

        return DB::transaction(function () use ($patient, $doctor, $type, $start, $end, $reason, $source) {
            self::lockAndCheck($doctor, $start, $end, null);

            return Appointment::create([
                'patient_id' => $patient->id, 'doctor_id' => $doctor->id, 'appointment_type_id' => $type?->id,
                'branch_id' => current_branch()->id, 'starts_at' => $start, 'ends_at' => $end,
                'status' => Appointment::BOOKED, 'source' => $source, 'reason' => $reason, 'created_by' => auth()->id(),
            ]);
        });
    }

    /** Move an appointment to another time or another doctor. */
    public static function reschedule(Appointment $appointment, Doctor $doctor, ?AppointmentType $type, Carbon $start, ?int $minutes = null, ?string $reason = null): Appointment
    {
        if (! $appointment->isOpen()) {
            throw new UserError(__('Only a booked or confirmed appointment can be moved.'));
        }
        $minutes = $minutes ?: ($type?->minutes ?: (int) setting('slot_minutes', '15'));
        $end = $start->copy()->addMinutes($minutes);

        return DB::transaction(function () use ($appointment, $doctor, $type, $start, $end, $reason) {
            self::lockAndCheck($doctor, $start, $end, $appointment->id);
            $appointment->update(['doctor_id' => $doctor->id, 'appointment_type_id' => $type?->id, 'starts_at' => $start, 'ends_at' => $end, 'reason' => $reason]);

            return $appointment;
        });
    }

    /**
     * A patient who came without an appointment: he is in the queue at once, with a token.
     * He takes no time on the calendar, so the doctor's booked patients are not pushed out.
     */
    public static function walkIn(Patient $patient, Doctor $doctor, ?AppointmentType $type, ?string $reason = null): Appointment
    {
        return DB::transaction(function () use ($patient, $doctor, $type, $reason) {
            $now = now();
            $minutes = $type?->minutes ?: (int) setting('slot_minutes', '15');

            return Appointment::create([
                'patient_id' => $patient->id, 'doctor_id' => $doctor->id, 'appointment_type_id' => $type?->id,
                'branch_id' => current_branch()->id, 'starts_at' => $now, 'ends_at' => $now->copy()->addMinutes($minutes),
                'status' => Appointment::ARRIVED, 'source' => 'walk_in', 'token' => self::nextToken($now), 'arrived_at' => $now,
                'reason' => $reason, 'created_by' => auth()->id(),
            ]);
        });
    }

    /** Take one of the actions of Appointment::ACTIONS (confirm, arrive, start, complete, no_show, cancel). */
    public static function act(Appointment $appointment, string $action, ?string $reason = null): Appointment
    {
        $rule = Appointment::ACTIONS[$action] ?? null;
        if (! $rule) {
            throw new UserError(__('Unknown action.'));
        }

        return DB::transaction(function () use ($appointment, $action, $rule, $reason) {
            $appointment = Appointment::lockForUpdate()->findOrFail($appointment->id);
            if (! in_array($appointment->status, $rule[0], true)) {
                throw new UserError(__('This appointment is :status; it cannot be changed this way.', ['status' => $appointment->statusLabel()]));
            }
            $now = now();
            $changes = ['status' => $rule[1]];
            if ($action === 'arrive') {
                $changes += ['arrived_at' => $now, 'token' => $appointment->token ?: self::nextToken($appointment->starts_at)];
            } elseif ($action === 'start') {
                $changes['started_at'] = $now;
            } elseif ($action === 'complete') {
                $changes += ['finished_at' => $now, 'started_at' => $appointment->started_at ?: $now];
            } elseif ($action === 'cancel') {
                $changes['cancel_reason'] = $reason ? mb_substr($reason, 0, 255) : null;
            }
            $appointment->update($changes);

            return $appointment;
        });
    }

    /**
     * The doctor calls the next patient: the one with him now is done, and the waiting patient
     * with the lowest token comes in. Returns the patient called, or null when nobody waits.
     */
    public static function callNext(Doctor $doctor, Carbon $day): ?Appointment
    {
        return DB::transaction(function () use ($doctor, $day) {
            Doctor::whereKey($doctor->id)->lockForUpdate()->first();
            $today = fn () => Appointment::where('doctor_id', $doctor->id)->where('branch_id', current_branch()->id)
                ->whereBetween('starts_at', [$day->copy()->startOfDay(), $day->copy()->endOfDay()]);

            foreach ($today()->where('status', Appointment::WITH_DOCTOR)->get() as $current) {
                $current->update(['status' => Appointment::COMPLETED, 'finished_at' => now()]);
            }
            $next = $today()->where('status', Appointment::ARRIVED)->orderBy('token')->orderBy('arrived_at')->first();
            $next?->update(['status' => Appointment::WITH_DOCTOR, 'started_at' => now()]);

            return $next;
        });
    }

    /** The next token of the day in the user's branch: 1, 2, 3 ... from the first patient of the morning. */
    public static function nextToken(Carbon $day): int
    {
        $last = Appointment::where('branch_id', current_branch()->id)
            ->whereBetween('starts_at', [$day->copy()->startOfDay(), $day->copy()->endOfDay()])
            ->lockForUpdate()->max('token');

        return ((int) $last) + 1;
    }

    /** Lock the doctor, so two people booking at once cannot both take the same time, then look for a clash. */
    private static function lockAndCheck(Doctor $doctor, Carbon $start, Carbon $end, ?int $except): void
    {
        $doctor = Doctor::whereKey($doctor->id)->lockForUpdate()->first();
        if (! $doctor?->is_active) {
            throw new UserError(__('This doctor does not see patients now.'));
        }
        $clash = Appointment::with('patient')->where('doctor_id', $doctor->id)
            ->where('source', '!=', 'walk_in')
            ->whereIn('status', Appointment::HOLDING)
            ->where('starts_at', '<', $end)->where('ends_at', '>', $start)
            ->when($except, fn ($q) => $q->whereKeyNot($except))
            ->first();
        if ($clash) {
            throw new UserError(__('The doctor already has :patient from :from to :to. Choose another time.', [
                'patient' => $clash->patient?->name, 'from' => $clash->starts_at->format('H:i'), 'to' => $clash->ends_at->format('H:i'),
            ]));
        }
    }
}
