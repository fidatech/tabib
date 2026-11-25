<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\Doctor;
use App\Models\Patient;
use App\Services\Scheduling;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The calendar of the day, booking and moving appointments, the buttons that take a patient
 * through his visit, walk-ins, and the queue the doctors call from.
 */
class AppointmentController extends Controller
{
    /** The calendar of one day: a column for every doctor, a row for every slot of the working hours. */
    public function index(Request $request): View
    {
        Gate::authorize('appointments.view');
        $day = $this->day($request);
        $doctors = Doctor::active();
        $mine = $doctors->firstWhere('user_id', $request->user()->id);

        // a doctor opens on his own column; ?doctor=all shows every doctor
        $chosen = (string) $request->query('doctor', $mine ? (string) $mine->id : 'all');
        $shown = $chosen === 'all' ? $doctors : $doctors->where('id', (int) $chosen)->values();
        if ($shown->isEmpty()) {
            $shown = $doctors;
            $chosen = 'all';
        }

        $appointments = Appointment::with(['patient', 'type', 'invoice'])
            ->where('branch_id', current_branch()->id)
            ->whereIn('doctor_id', $shown->pluck('id'))
            ->whereBetween('starts_at', [$day->copy()->startOfDay(), $day->copy()->endOfDay()])
            ->orderBy('starts_at')->get();

        // the rows: the working hours of the clinic, stretched to fit an appointment outside them
        $step = max(5, (int) setting('slot_minutes', '15'));
        $open = $day->copy()->setTimeFromTimeString(setting('opening_time', '08:00'));
        $close = $day->copy()->setTimeFromTimeString(setting('closing_time', '17:00'));
        $kept = $appointments->where('status', '!=', Appointment::CANCELLED);
        if ($kept->isNotEmpty()) {
            $open = $open->min($kept->min('starts_at'));
            $close = $close->max($kept->max('ends_at'));
        }
        $open = $open->copy()->setTime($open->hour, intdiv($open->minute, $step) * $step);
        $slots = [];
        for ($t = $open->copy(); $t < $close && count($slots) < 200; $t->addMinutes($step)) {
            $slots[] = $t->copy();
        }

        // the slots each doctor does not work (outside his hours, or a day off) are shown shaded
        $off = [];
        $leaves = [];
        foreach ($shown as $d) {
            $leaves[$d->id] = \App\Services\Availability::leave($d, $day);
            foreach ($slots as $slot) {
                if (! \App\Services\Availability::works($d, $slot, $slot->copy()->addMinutes($step))) {
                    $off[$d->id][$slot->format('H:i')] = true;
                }
            }
        }

        $cells = [];
        foreach ($appointments as $a) {
            $slot = $open->copy()->addMinutes(intdiv(max(0, (int) $open->diffInMinutes($a->starts_at)), $step) * $step);
            $cells[$a->doctor_id][$slot->format('H:i')][] = $a;
        }

        return view('appointments.index', [
            'day' => $day, 'doctors' => $doctors, 'shown' => $shown, 'chosen' => $chosen, 'slots' => $slots, 'cells' => $cells, 'step' => $step, 'off' => $off, 'leaves' => $leaves,
            'counts' => [
                'all' => $appointments->where('status', '!=', Appointment::CANCELLED)->count(),
                'waiting' => $appointments->where('status', Appointment::ARRIVED)->count(),
                'done' => $appointments->where('status', Appointment::COMPLETED)->count(),
            ],
            'canManage' => $request->user()->can('appointments.manage'),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('appointments.manage');

        return $this->form($request, null);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('appointments.manage');
        $walkIn = $request->boolean('walk_in');
        $data = $this->validated($request, $walkIn);
        $patient = Patient::findOrFail($data['patient_id']);
        $doctor = Doctor::findOrFail($data['doctor_id']);
        $type = isset($data['appointment_type_id']) ? AppointmentType::find($data['appointment_type_id']) : null;

        if ($walkIn) {
            $a = Scheduling::walkIn($patient, $doctor, $type, $data['reason'] ?? null);

            return redirect()->route('queue.index')->with('success', __(':patient is in the queue of :doctor with token :token.', ['patient' => $patient->name, 'doctor' => $doctor->name(), 'token' => $a->token]));
        }

        $outside = $request->boolean('outside_hours');
        $count = (int) ($data['repeat_count'] ?? 1);
        if (($data['repeat'] ?? '') !== '' && $count > 1) {
            // a course of appointments: every so many days, the same time
            $every = match ($data['repeat']) { 'daily' => 1, 'weekly' => 7, default => max(1, (int) ($data['repeat_every'] ?? 2)) };
            [$booked, $skipped] = Scheduling::bookSeries($patient, $doctor, $type, $this->start($data), $data['minutes'] ?? null, $data['reason'] ?? null, $every, $count, $outside);
            if (! $booked) {
                throw new \App\Exceptions\UserError(__('None of the appointments could be booked.').' '.implode(' · ', $skipped));
            }
            \App\Services\Sms::booked($booked[0], $request->user()->id);
            $redirect = redirect()->route('appointments.index', ['date' => $booked[0]->starts_at->toDateString()])
                ->with('success', __(':count appointments booked for :patient, from :first to :last.', ['count' => count($booked), 'patient' => $patient->name,
                    'first' => fmt_date($booked[0]->starts_at), 'last' => fmt_date(end($booked)->starts_at)]));

            return $skipped ? $redirect->with('warning', __('Not booked:').' '.implode(' · ', $skipped)) : $redirect;
        }

        $a = Scheduling::book($patient, $doctor, $type, $this->start($data), $data['minutes'] ?? null, $data['reason'] ?? null, 'desk', $outside);
        \App\Services\Sms::booked($a, $request->user()->id);

        return redirect()->route('appointments.index', ['date' => $a->starts_at->toDateString()])
            ->with('success', __(':patient is booked with :doctor at :time.', ['patient' => $patient->name, 'doctor' => $doctor->name(), 'time' => $a->starts_at->format('H:i')]));
    }

    public function edit(Request $request, int $id): View
    {
        Gate::authorize('appointments.manage');
        $appointment = Appointment::with(['patient', 'doctor.user', 'type'])->findOrFail($id);
        abort_unless($appointment->isOpen(), 403);

        return $this->form($request, $appointment);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        Gate::authorize('appointments.manage');
        $appointment = Appointment::findOrFail($id);
        $data = $this->validated($request, false);
        $type = isset($data['appointment_type_id']) ? AppointmentType::find($data['appointment_type_id']) : null;
        $before = $appointment->starts_at->copy();
        Scheduling::reschedule($appointment, Doctor::findOrFail($data['doctor_id']), $type, $this->start($data), $data['minutes'] ?? null, $data['reason'] ?? null, $request->boolean('outside_hours'));
        if (! $appointment->fresh()->starts_at->equalTo($before)) {
            // a moved appointment is reminded again, of its new time
            Appointment::whereKey($appointment->id)->update(['reminded_at' => null]);
        }

        return redirect()->route('appointments.index', ['date' => $appointment->starts_at->toDateString()])->with('success', __('The appointment was moved.'));
    }

    /** The appointments of a course that are still to come are cancelled together. */
    public function cancelSeries(Request $request, int $id): RedirectResponse
    {
        Gate::authorize('appointments.manage');
        $appointment = Appointment::findOrFail($id);
        abort_unless($appointment->series_id, 404);
        $reason = $request->validate(['reason' => ['required', 'string', 'max:255']])['reason'];
        $n = 0;
        foreach (Appointment::where('series_id', $appointment->series_id)->where('starts_at', '>=', $appointment->starts_at)->whereIn('status', [Appointment::BOOKED, Appointment::CONFIRMED])->get() as $a) {
            Scheduling::act($a, 'cancel', $reason);
            $n++;
        }

        return back()->with('success', __(':count appointments of the course were cancelled.', ['count' => $n]));
    }

    /** The buttons of an appointment: confirm, arrived, call in, done, did not come, cancel. */
    public function act(Request $request, int $id, string $action): RedirectResponse
    {
        Gate::authorize('appointments.manage');
        $appointment = Scheduling::act(Appointment::findOrFail($id), $action, $request->input('reason'));
        $message = $action === 'arrive'
            ? __(':patient is waiting with token :token.', ['patient' => $appointment->patient->name, 'token' => $appointment->token])
            : __('The appointment is now: :status.', ['status' => $appointment->statusLabel()]);

        return back()->with('success', $message);
    }

    /** Today's queue: for every doctor, who is with him, who waits (by token) and who is still to come. */
    public function queue(Request $request): View
    {
        Gate::authorize('appointments.view');
        $day = Carbon::parse(today_iso());
        $today = Appointment::with(['patient', 'type', 'invoice'])->where('branch_id', current_branch()->id)
            ->whereBetween('starts_at', [$day->copy()->startOfDay(), $day->copy()->endOfDay()])
            ->orderBy('starts_at')->get()->groupBy('doctor_id');

        $board = Doctor::active()->map(fn (Doctor $d) => [
            'doctor' => $d,
            'current' => ($today[$d->id] ?? collect())->where('status', Appointment::WITH_DOCTOR)->values(),
            'waiting' => ($today[$d->id] ?? collect())->where('status', Appointment::ARRIVED)->sortBy('token')->values(),
            'coming' => ($today[$d->id] ?? collect())->whereIn('status', [Appointment::BOOKED, Appointment::CONFIRMED])->values(),
            'done' => ($today[$d->id] ?? collect())->where('status', Appointment::COMPLETED)->count(),
        ]);
        $mine = $board->firstWhere(fn (array $row) => $row['doctor']->user_id === $request->user()->id);

        return view('appointments.queue', [
            'board' => $mine ? collect([$mine])->merge($board->reject(fn (array $row) => $row === $mine))->values() : $board,
            'canManage' => $request->user()->can('appointments.manage'),
            'mine' => $mine['doctor']->id ?? null,
        ]);
    }

    public function callNext(Request $request, int $doctor): RedirectResponse
    {
        Gate::authorize('appointments.manage');
        $next = Scheduling::callNext(Doctor::findOrFail($doctor), Carbon::parse(today_iso()));

        return back()->with($next ? 'success' : 'info', $next
            ? __('Token :token, :patient, please come in.', ['token' => $next->token, 'patient' => $next->patient->name])
            : __('Nobody is waiting.'));
    }

    private function form(Request $request, ?Appointment $appointment): View
    {
        // the patient: of the appointment, the one the form was sent with before (after a mistake), or ?patient=
        $wanted = $request->old('patient_id', $request->query('patient'));
        $patient = $appointment?->patient ?? ($wanted ? Patient::find((int) $wanted) : null);

        return view('appointments.form', [
            'appointment' => $appointment,
            'patient' => $patient,
            'doctors' => Doctor::active(),
            'types' => AppointmentType::where('is_active', true)->orderBy('name')->get(),
            'walkIn' => ! $appointment && $request->boolean('walk_in'),
            'date' => $appointment?->starts_at->toDateString() ?? ($request->query('date') ?: today_iso()),
            'time' => $appointment?->starts_at->format('H:i') ?? $request->query('time'),
            'doctorId' => $appointment?->doctor_id ?? (int) $request->query('doctor') ?: Doctor::active()->firstWhere('user_id', $request->user()->id)?->id,
        ]);
    }

    private function validated(Request $request, bool $walkIn): array
    {
        return $request->validate([
            'patient_id' => ['required', 'integer', Rule::exists('patients', 'id')->whereNull('deleted_at')],
            'doctor_id' => ['required', 'integer', Rule::exists('doctors', 'id')->where('is_active', true)],
            'appointment_type_id' => ['nullable', 'integer', Rule::exists('appointment_types', 'id')->where('is_active', true)],
            'date' => [$walkIn ? 'nullable' : 'required', 'date', 'after_or_equal:'.today_iso()],
            'time' => [$walkIn ? 'nullable' : 'required', 'date_format:H:i'],
            'minutes' => ['nullable', 'integer', 'min:5', 'max:480'],
            'reason' => ['nullable', 'string', 'max:255'],
            'repeat' => ['nullable', Rule::in(['', 'daily', 'weekly', 'every'])],
            'repeat_every' => ['nullable', 'integer', 'min:1', 'max:60'],
            'repeat_count' => ['nullable', 'integer', 'min:1', 'max:30'],
        ], [], ['patient_id' => __('patient'), 'doctor_id' => __('doctor')]);
    }

    private function start(array $data): Carbon
    {
        return Carbon::parse($data['date'].' '.$data['time'], config('app.timezone'));
    }

    private function day(Request $request): Carbon
    {
        $date = (string) $request->query('date', '');

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && strtotime($date) ? Carbon::parse($date) : Carbon::parse(today_iso());
    }
}
