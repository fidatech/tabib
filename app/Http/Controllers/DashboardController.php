<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\Branch;
use App\Models\Doctor;
use App\Models\Notice;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $branches = Branch::withCount('users')->orderByRaw("case type when 'main' then 0 else 1 end")->orderBy('name')->get();
        $staff = User::where('is_active', true)->count();
        $doctors = Doctor::where('is_active', true)->count();

        // what a new clinic still has to do before its first patient: each step is done or not, with the page that does it
        $steps = [];
        if ($user->can('settings.view')) {
            $steps[] = ['Add the logo and contact details of the clinic', (bool) (setting('company_logo') && setting('company_phone')), route('settings.index'), 'image'];
            $steps[] = ['Write the health ministry licence number', (bool) setting('licence_no'), route('settings.index'), 'file-medical'];
        }
        if ($user->can('users.view')) {
            $steps[] = ['Add the doctors of the clinic', $doctors > 0, route('doctors.create'), 'user-doctor'];
        }
        if ($user->can('settings.view')) {
            $steps[] = ['Write the kinds of appointment and their prices', AppointmentType::exists(), route('appointment-types.create'), 'calendar-check'];
        }
        if ($user->can('users.view')) {
            $steps[] = ['Add the front desk and the nurses', $staff > $doctors + 1, route('users.create'), 'users'];
        }
        if ($user->can('patients.manage')) {
            $steps[] = ['Register the first patient', Patient::exists(), route('patients.create'), 'hospital-user'];
        }

        // today, in the user's branch
        $today = Carbon::parse(today_iso());
        $day = Appointment::where('branch_id', current_branch()->id)->whereBetween('starts_at', [$today->copy()->startOfDay(), $today->copy()->endOfDay()]);
        $tiles = [];
        if ($user->can('appointments.view')) {
            $tiles[] = ['Appointments today', (clone $day)->where('status', '!=', Appointment::CANCELLED)->count(), 'calendar-days', 'primary', route('appointments.index')];
            $tiles[] = ['Waiting now', (clone $day)->where('status', Appointment::ARRIVED)->count(), 'hourglass-half', 'warning', route('queue.index')];
            $tiles[] = ['Seen today', (clone $day)->where('status', Appointment::COMPLETED)->count(), 'circle-check', 'success', route('queue.index')];
        }
        if ($user->can('patients.view')) {
            $tiles[] = ['New patients this month', Patient::where('created_at', '>=', $today->copy()->startOfMonth())->count(), 'user-plus', 'info', route('patients.index')];
        }
        if (! $tiles) {
            $tiles[] = ['Doctors', $doctors, 'user-doctor', 'primary', null];
            $tiles[] = ['Staff who sign in', $staff, 'users', 'success', null];
        }

        return view('dashboard', [
            'branches' => $branches,
            'tiles' => $tiles,
            'steps' => $steps,
            'notices' => Notice::where('user_id', $user->id)->whereNull('read_at')->orderByDesc('id')->limit(5)->get(),
        ]);
    }
}
