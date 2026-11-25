<?php

namespace App\Services;

use App\Exceptions\UserError;
use App\Models\Doctor;
use App\Models\DoctorHour;
use App\Models\DoctorLeave;
use Illuminate\Support\Carbon;

/**
 * When a doctor can be booked: his hours for each day of the week (when none are written, the
 * hours of the clinic, every day), less his days off.
 */
class Availability
{
    public const WEEKDAYS = [6 => 'Saturday', 0 => 'Sunday', 1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday'];

    /** @return list<array{0: Carbon, 1: Carbon}> the periods the doctor works on that day */
    public static function periods(Doctor $doctor, Carbon $day): array
    {
        if (self::leave($doctor, $day)) {
            return [];
        }
        // the hours of a doctor are read once a request (the calendar asks for every slot)
        $key = 'doctor_hours_'.$doctor->id;
        if (! request()->attributes->has($key)) {
            request()->attributes->set($key, DoctorHour::where('doctor_id', $doctor->id)->orderBy('starts')->get());
        }
        $rows = request()->attributes->get($key);
        $at = fn (string $time) => $day->copy()->setTimeFromTimeString($time);
        if ($rows->isEmpty()) {
            return [[$at(setting('opening_time', '08:00')), $at(setting('closing_time', '17:00'))]];
        }

        return $rows->where('weekday', $day->dayOfWeek)->map(fn (DoctorHour $h) => [$at($h->starts), $at($h->ends)])->values()->all();
    }

    public static function leave(Doctor $doctor, Carbon $day): ?DoctorLeave
    {
        $key = 'doctor_leave_'.$doctor->id.'_'.$day->toDateString();
        if (! request()->attributes->has($key)) {
            request()->attributes->set($key, DoctorLeave::where('doctor_id', $doctor->id)->whereDate('starts_on', '<=', $day->toDateString())->whereDate('ends_on', '>=', $day->toDateString())->first());
        }

        return request()->attributes->get($key);
    }

    public static function works(Doctor $doctor, Carbon $start, Carbon $end): bool
    {
        foreach (self::periods($doctor, $start) as [$from, $to]) {
            if ($start->gte($from) && $end->lte($to)) {
                return true;
            }
        }

        return false;
    }

    /** Stop a booking the doctor cannot keep, saying why. */
    public static function check(Doctor $doctor, Carbon $start, Carbon $end): void
    {
        if ($leave = self::leave($doctor, $start)) {
            throw new UserError(__(':doctor is off from :from to :to:reason.', ['doctor' => $doctor->name(), 'from' => fmt_date($leave->starts_on), 'to' => fmt_date($leave->ends_on),
                'reason' => $leave->reason ? ' ('.$leave->reason.')' : '']));
        }
        if (! self::works($doctor, $start, $end)) {
            $periods = array_map(fn ($p) => $p[0]->format('H:i').'–'.$p[1]->format('H:i'), self::periods($doctor, $start));
            throw new UserError($periods
                ? __(':doctor works :hours on that day. Choose a time in those hours, or tick "outside the hours".', ['doctor' => $doctor->name(), 'hours' => implode(', ', $periods)])
                : __(':doctor does not work on :day. Choose another day, or tick "outside the hours".', ['doctor' => $doctor->name(), 'day' => __(self::WEEKDAYS[$start->dayOfWeek])]));
        }
    }
}
