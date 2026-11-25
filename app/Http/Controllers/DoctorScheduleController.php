<?php

namespace App\Http\Controllers;

use App\Exceptions\UserError;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\DoctorHour;
use App\Models\DoctorLeave;
use App\Services\Availability;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** When a doctor works, day by day of the week (up to two periods: morning and afternoon), and his days off. */
class DoctorScheduleController extends Controller
{
    public function show(int $id): View
    {
        Gate::authorize('appointments.view');
        $doctor = Doctor::with('user')->findOrFail($id);

        return view('doctors.schedule', [
            'doctor' => $doctor,
            'hours' => DoctorHour::where('doctor_id', $doctor->id)->orderBy('starts')->get()->groupBy('weekday'),
            'leaves' => DoctorLeave::where('doctor_id', $doctor->id)->where('ends_on', '>=', \Illuminate\Support\Carbon::parse(today_iso())->subDays(60)->toDateString())->orderByDesc('starts_on')->get(),
            'canManage' => request()->user()->can('appointments.manage'),
        ]);
    }

    /** The week: for every day, a first period and an optional second one. All days empty: the hours of the clinic. */
    public function hours(Request $request, int $id): RedirectResponse
    {
        Gate::authorize('appointments.manage');
        $doctor = Doctor::findOrFail($id);
        $rules = [];
        foreach (array_keys(Availability::WEEKDAYS) as $d) {
            foreach ([1, 2] as $p) {
                $rules["days.$d.$p.from"] = ['nullable', 'date_format:H:i', "required_with:days.$d.$p.to"];
                $rules["days.$d.$p.to"] = ['nullable', 'date_format:H:i', "required_with:days.$d.$p.from", "after:days.$d.$p.from"];
            }
        }
        $days = (array) ($request->validate($rules)['days'] ?? []);
        $rows = [];
        foreach ($days as $weekday => $periods) {
            foreach ((array) $periods as $p) {
                if (! empty($p['from']) && ! empty($p['to'])) {
                    $rows[] = ['doctor_id' => $doctor->id, 'weekday' => (int) $weekday, 'starts' => $p['from'], 'ends' => $p['to'], 'created_at' => now(), 'updated_at' => now()];
                }
            }
        }
        // the two periods of one day must not overlap
        foreach (collect($rows)->groupBy('weekday') as $weekday => $list) {
            $sorted = $list->sortBy('starts')->values();
            if ($sorted->count() === 2 && $sorted[1]['starts'] < $sorted[0]['ends']) {
                throw new UserError(__('The two periods of :day overlap.', ['day' => __(Availability::WEEKDAYS[$weekday])]));
            }
        }
        DB::transaction(function () use ($doctor, $rows) {
            DoctorHour::where('doctor_id', $doctor->id)->delete();
            if ($rows) {
                DoctorHour::insert($rows);
            }
        });
        audit('updated', $doctor, 'Working hours of '.$doctor->name());

        return redirect()->route('doctors.schedule', ['id' => $doctor->id])->with('success', $rows ? __('The hours are saved.') : __('No hours written: the doctor works the hours of the clinic.'));
    }

    /** Days off; the appointments already booked in them are shown, so the desk can move them. */
    public function addLeave(Request $request, int $id): RedirectResponse
    {
        Gate::authorize('appointments.manage');
        $doctor = Doctor::findOrFail($id);
        $data = $request->validate(['starts_on' => ['required', 'date'], 'ends_on' => ['required', 'date', 'after_or_equal:starts_on'], 'reason' => ['nullable', 'string', 'max:150']]);
        DoctorLeave::create($data + ['doctor_id' => $doctor->id, 'created_by' => $request->user()->id]);
        $booked = Appointment::where('doctor_id', $doctor->id)->whereIn('status', [Appointment::BOOKED, Appointment::CONFIRMED])
            ->whereBetween('starts_at', [\Illuminate\Support\Carbon::parse($data['starts_on'])->startOfDay(), \Illuminate\Support\Carbon::parse($data['ends_on'])->endOfDay()])->count();

        $back = redirect()->route('doctors.schedule', ['id' => $doctor->id])->with('success', __('The days off are saved.'));

        return $booked ? $back->with('warning', __(':count appointments are already booked on these days: move them to another doctor or day.', ['count' => $booked])) : $back;
    }

    public function removeLeave(int $id, int $leave): RedirectResponse
    {
        Gate::authorize('appointments.manage');
        DoctorLeave::where('doctor_id', $id)->findOrFail($leave)->delete();

        return redirect()->route('doctors.schedule', ['id' => $id])->with('success', __('The days off are removed.'));
    }
}
