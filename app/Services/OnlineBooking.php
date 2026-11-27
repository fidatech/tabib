<?php

namespace App\Services;

use App\Exceptions\UserError;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\OnlineBooking as Request;
use App\Models\Patient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * The rules of booking from the public page: which doctors and days are open, the free times, the
 * SMS code, and the appointment it makes. Nothing about other patients is ever shown or said here.
 */
class OnlineBooking
{
    public const CODE_MINUTES = 15;

    public const MAX_ATTEMPTS = 5;

    public const MAX_OPEN_PER_PHONE = 2;

    /** Open only when the clinic switched it on and its SMS really goes out (the code must reach the patient). */
    public static function open(): bool
    {
        return setting('online_booking') === '1' && Sms::enabled() && setting('sms_gateway', 'log') !== 'log';
    }

    public static function doctors(): Collection
    {
        return Doctor::with('user')->where('is_active', true)->where('books_online', true)->get()->sortBy(fn (Doctor $d) => $d->name())->values();
    }

    /** @return list<Carbon> the days that can be chosen */
    public static function days(): array
    {
        $days = [];
        $today = Carbon::parse(today_iso());
        for ($i = 0; $i <= max(1, min(60, (int) setting('online_days_ahead', '14'))); $i++) {
            $days[] = $today->copy()->addDays($i);
        }

        return $days;
    }

    public static function minutes(): int
    {
        return max(5, (int) setting('slot_minutes', '15'));
    }

    /** @return list<Carbon> the free starting times of a doctor on a day */
    public static function slots(Doctor $doctor, Carbon $day): array
    {
        $step = self::minutes();
        $earliest = now()->addHours(max(0, (int) setting('online_min_hours', '2')));
        $taken = Appointment::where('doctor_id', $doctor->id)->where('source', '!=', 'walk_in')->whereIn('status', Appointment::HOLDING)
            ->whereBetween('starts_at', [$day->copy()->startOfDay(), $day->copy()->endOfDay()])->get(['starts_at', 'ends_at']);
        $held = Request::where('doctor_id', $doctor->id)->whereNull('confirmed_at')->where('code_expires_at', '>', now())
            ->whereBetween('starts_at', [$day->copy()->startOfDay(), $day->copy()->endOfDay()])->pluck('starts_at');
        $free = [];
        foreach (Availability::periods($doctor, $day) as [$from, $to]) {
            for ($t = $from->copy(); $t->copy()->addMinutes($step)->lte($to); $t->addMinutes($step)) {
                $end = $t->copy()->addMinutes($step);
                $busy = $taken->contains(fn ($a) => $a->starts_at->lt($end) && $a->ends_at->gt($t)) || $held->contains(fn ($h) => $h->equalTo($t));
                if ($t->gte($earliest) && ! $busy) {
                    $free[] = $t->copy();
                }
            }
        }

        return $free;
    }

    /** The time is held for the patient while the code goes to his phone. */
    public static function ask(Doctor $doctor, Carbon $start, array $person, ?string $ip): Request
    {
        if (! self::open() || ! $doctor->books_online || ! $doctor->is_active) {
            throw new UserError(__('Booking online is not open now. Please call the clinic.'));
        }
        $phone = Sms::number($person['phone']) ?? throw new UserError(__('Write a mobile number: the code is sent to it.'));
        if (! collect(self::slots($doctor, $start->copy()->startOfDay()))->contains(fn (Carbon $s) => $s->equalTo($start))) {
            throw new UserError(__('That time is not free any more. Please choose another one.'));
        }
        $open = Request::where('phone', $phone)->whereNotNull('appointment_id')->whereHas('appointment', fn ($q) => $q->whereIn('status', [Appointment::BOOKED, Appointment::CONFIRMED])
            ->where('starts_at', '>', now()))->count();
        if ($open >= self::MAX_OPEN_PER_PHONE) {
            throw new UserError(__('This phone already has :count appointments booked online. Please call the clinic for more.', ['count' => $open]));
        }
        $code = (string) random_int(100000, 999999);
        $request = Request::create([
            'token' => Str::lower(Str::random(40)), 'doctor_id' => $doctor->id, 'starts_at' => $start, 'name' => $person['name'], 'phone' => $phone,
            'gender' => $person['gender'], 'age' => $person['age'] ?? null, 'reason' => $person['reason'] ?? null,
            'code_hash' => Hash::make($code), 'code_expires_at' => now()->addMinutes(self::CODE_MINUTES), 'ip' => $ip,
        ]);
        $clinic = setting('company_name', config('app.name'));
        $sent = Sms::send($phone, __(':code is your code to book at :clinic. It is good for :minutes minutes.', ['code' => $code, 'clinic' => $clinic, 'minutes' => self::CODE_MINUTES]), 'code');
        // the list of messages keeps the message, never the code
        $sent->update(['body' => str_replace($code, '······', $sent->body)]);
        if ($sent->status === 'failed') {
            $request->update(['code_expires_at' => now()]);   // the time is not held for a code that did not go
            throw new UserError(__('The code could not be sent to :phone. Please call the clinic.', ['phone' => $phone]));
        }

        return $request;
    }

    /** The code is right: the patient is found (or registered) and the appointment made. */
    public static function confirm(Request $request, string $code): Appointment
    {
        if ($request->appointment_id) {
            return $request->appointment;
        }
        if ($request->code_expires_at->isPast()) {
            throw new UserError(__('The code is too old. Please book again.'));
        }
        if ($request->attempts >= self::MAX_ATTEMPTS) {
            throw new UserError(__('Too many wrong codes. Please book again.'));
        }
        if (! Hash::check(preg_replace('/\D+/', '', strtr($code, ['۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9'])), $request->code_hash)) {
            $request->increment('attempts');
            throw new UserError(__('The code is not right. :left tries left.', ['left' => self::MAX_ATTEMPTS - $request->attempts]));
        }

        return DB::transaction(function () use ($request) {
            $patient = self::patient($request);
            try {
                $a = Scheduling::book($patient, $request->doctor, null, $request->starts_at, self::minutes(), $request->reason, 'online');
            } catch (UserError) {
                throw new UserError(__('That time is not free any more. Please choose another one.'));   // the name of who took it is not told
            }
            $request->update(['confirmed_at' => now(), 'appointment_id' => $a->id]);
            Sms::booked($a);

            return $a;
        });
    }

    public static function cancel(Request $request): void
    {
        $a = $request->appointment;
        if (! $a || ! in_array($a->status, [Appointment::BOOKED, Appointment::CONFIRMED], true)) {
            throw new UserError(__('This appointment cannot be cancelled here. Please call the clinic.'));
        }
        if ($a->starts_at->lte(now()->addHour())) {
            throw new UserError(__('It is too late to cancel online. Please call the clinic.'));
        }
        Scheduling::act($a, 'cancel', __('Cancelled by the patient online'));
    }

    /** The same person: the same mobile and a name that matches; otherwise a new record, marked as registered online. */
    private static function patient(Request $request): Patient
    {
        $digits = substr(preg_replace('/\D+/', '', $request->phone), -9);
        $name = mb_strtolower(trim($request->name));
        foreach (Patient::where('phone', 'like', '%'.$digits)->orWhere('phone2', 'like', '%'.$digits)->get() as $p) {
            if (mb_strtolower(trim($p->name)) === $name) {
                return $p;
            }
        }

        $country = '+'.(preg_replace('/\D+/', '', (string) setting('sms_country_code', '93')) ?: '93');
        $local = str_starts_with($request->phone, $country) ? '0'.substr($request->phone, strlen($country)) : $request->phone;

        return Patient::create([
            'number' => Sequence::next('P'), 'name' => $request->name, 'gender' => $request->gender, 'phone' => $local,
            'date_of_birth' => $request->age !== null ? Carbon::parse(today_iso())->subYears($request->age)->startOfYear()->toDateString() : null,
            'dob_estimated' => $request->age !== null, 'registered_online' => true,
        ]);
    }
}
