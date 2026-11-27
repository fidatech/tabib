<?php

namespace App\Http\Controllers;

use App\Models\Encounter;
use App\Services\Sms;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * The patients the doctors asked to come back, from two weeks ago to two weeks ahead: who has an
 * appointment, who was reminded, and who the desk should call.
 */
class FollowUpController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('appointments.view');
        $today = Carbon::parse(today_iso());
        $show = (string) $request->query('show', 'open');
        $visits = Encounter::with(['patient', 'doctor.user'])->where('status', 'signed')->whereNotNull('follow_up_on')
            ->where('branch_id', viewed_branch()->id)
            ->whereDate('follow_up_on', '>=', $today->copy()->subDays(14)->toDateString())->whereDate('follow_up_on', '<=', $today->copy()->addDays(14)->toDateString())
            ->orderBy('follow_up_on')->get()
            ->map(fn (Encounter $v) => ['visit' => $v, 'booked' => Sms::hasAppointmentNear($v)]);
        if ($show === 'open') {
            $visits = $visits->reject(fn ($r) => $r['booked'])->values();
        }

        return view('follow-ups.index', ['rows' => $visits, 'show' => $show, 'today' => $today, 'canBook' => $request->user()->can('appointments.manage')]);
    }
}
