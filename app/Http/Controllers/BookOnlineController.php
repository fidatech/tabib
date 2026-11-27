<?php

namespace App\Http\Controllers;

use App\Exceptions\UserError;
use App\Models\Doctor;
use App\Models\OnlineBooking as Booking;
use App\Services\OnlineBooking;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** The public page where a patient books: no sign-in, a code by SMS, and the address of his booking to see or cancel it. */
class BookOnlineController extends Controller
{
    public function index(Request $request): View
    {
        $open = OnlineBooking::open();
        $doctors = $open ? OnlineBooking::doctors() : collect();
        $doctor = $doctors->firstWhere('id', $request->integer('doctor')) ?? ($doctors->count() === 1 ? $doctors->first() : null);
        $days = OnlineBooking::days();
        $day = collect($days)->first(fn (Carbon $d) => $d->toDateString() === $request->query('date')) ?? ($doctor ? collect($days)->first(fn (Carbon $d) => OnlineBooking::slots($doctor, $d)) : null);

        return view('book.index', ['open' => $open, 'doctors' => $doctors, 'doctor' => $doctor, 'days' => $days, 'day' => $day,
            'slots' => $doctor && $day ? OnlineBooking::slots($doctor, $day) : [], 'time' => (string) $request->query('time', '')]);
    }

    public function ask(Request $request): RedirectResponse
    {
        if (filled($request->input('website'))) {
            abort(422);   // the box people do not see: filled only by programs
        }
        $data = $request->validate([
            'doctor_id' => ['required', 'integer'], 'date' => ['required', 'date_format:Y-m-d'], 'time' => ['required', 'date_format:H:i'],
            'name' => ['required', 'string', 'min:2', 'max:150'], 'phone' => ['required', 'string', 'max:30'], 'gender' => ['required', Rule::in(['male', 'female'])],
            'age' => ['nullable', 'integer', 'min:0', 'max:120'], 'reason' => ['nullable', 'string', 'max:255'],
        ]);
        $doctor = Doctor::where('is_active', true)->findOrFail($data['doctor_id']);
        try {
            $booking = OnlineBooking::ask($doctor, Carbon::parse($data['date'].' '.$data['time']), $data, $request->ip());
        } catch (UserError $e) {
            return redirect()->route('book.index', ['doctor' => $doctor->id, 'date' => $data['date']])->withInput()->with('danger', $e->getMessage());
        }

        return redirect()->route('book.code', ['token' => $booking->token]);
    }

    public function code(string $token): View|RedirectResponse
    {
        $booking = Booking::with('doctor.user')->where('token', $token)->firstOrFail();
        if ($booking->appointment_id) {
            return redirect()->route('book.show', ['token' => $token]);
        }

        return view('book.code', ['booking' => $booking]);
    }

    public function confirm(Request $request, string $token): RedirectResponse
    {
        $booking = Booking::with('doctor')->where('token', $token)->firstOrFail();
        $code = $request->validate(['code' => ['required', 'string', 'max:12']])['code'];
        try {
            OnlineBooking::confirm($booking, $code);
        } catch (UserError $e) {
            return back()->with('danger', $e->getMessage());
        }

        return redirect()->route('book.show', ['token' => $token])->with('success', __('Your appointment is booked.'));
    }

    public function show(string $token): View
    {
        $booking = Booking::with(['doctor.user', 'appointment'])->where('token', $token)->whereNotNull('appointment_id')->firstOrFail();

        return view('book.show', ['booking' => $booking, 'a' => $booking->appointment]);
    }

    public function cancel(string $token): RedirectResponse
    {
        $booking = Booking::with('appointment')->where('token', $token)->whereNotNull('appointment_id')->firstOrFail();
        try {
            OnlineBooking::cancel($booking);
        } catch (UserError $e) {
            return back()->with('danger', $e->getMessage());
        }

        return redirect()->route('book.show', ['token' => $token])->with('success', __('Your appointment is cancelled.'));
    }
}
