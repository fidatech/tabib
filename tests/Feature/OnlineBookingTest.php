<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\OnlineBooking;
use App\Models\Patient;
use App\Models\Setting;
use App\Models\SmsMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** The public page of a clinic where patients book, confirming with a code sent by SMS. (The clock stands at Friday 2026-11-13 10:00.) */
class OnlineBookingTest extends TestCase
{
    use RefreshDatabase;

    private string $code = '';

    private function clinicOpen(): Doctor
    {
        $this->clinic();
        $this->owner();
        $this->post('/doctors', ['name' => 'Dr. Ahmad', 'email' => 'ahmad@acme.test', 'password' => 'doctor-pass-1', 'fee' => '300', 'colour' => 'teal', 'is_active' => '1', 'books_online' => '1']);
        $this->put('/doctors/1/schedule', ['days' => [6 => [1 => ['from' => '08:00', 'to' => '10:00']]]]);   // Saturdays 08:00-10:00 only
        Http::fake(['sms.example.af/*' => function (HttpRequest $r) {
            if (preg_match('/text=(\d{6}) /', urldecode($r->url()), $m)) {
                $this->code = $m[1];
            }

            return Http::response('OK');
        }]);
        $this->put('/settings/sms', ['sms_enabled' => '1', 'sms_gateway' => 'http', 'sms_country_code' => '93', 'sms_http_url' => 'https://sms.example.af/send?to={to}&text={message}',
            'sms_http_method' => 'get', 'sms_language' => 'en', 'sms_reminder_time' => '16:00', 'online_booking' => '1', 'online_days_ahead' => '14', 'online_min_hours' => '2']);
        auth()->logout();

        return Doctor::sole();
    }

    private function ask(Doctor $d, string $time = '08:30', array $more = [])
    {
        return $this->post('/book', $more + ['doctor_id' => $d->id, 'date' => '2026-11-14', 'time' => $time, 'name' => 'Mariam Karimi', 'phone' => '0799 111 222', 'gender' => 'female', 'age' => '30']);
    }

    public function test_a_patient_books_with_the_code_and_the_desk_sees_it(): void
    {
        $d = $this->clinicOpen();
        $this->get('/book?doctor='.$d->id.'&date=2026-11-14')->assertOk()->assertSee('Dr. Ahmad')->assertSee('08:00')->assertSee('09:45')->assertDontSee('10:00</label>', false);
        $this->get('/book?doctor='.$d->id.'&date=2026-11-15')->assertOk()->assertSee('No free time on this day');   // Sunday: he does not work

        $this->ask($d)->assertRedirect();
        $booking = OnlineBooking::sole();
        $this->assertMatchesRegularExpression('/^\d{6}$/', $this->code);
        $this->assertStringNotContainsString($this->code, SmsMessage::where('kind', 'code')->sole()->body);   // the list keeps no code
        // the time is held while the code is on its way
        $this->get('/book?doctor='.$d->id.'&date=2026-11-14')->assertDontSee('for="t-0830"', false);

        $this->post('/book/'.$booking->token.'/code', ['code' => '000000'])->assertSessionHas('danger');
        $this->post('/book/'.$booking->token.'/code', ['code' => $this->code])->assertRedirect('/book/'.$booking->token);
        $a = Appointment::sole();
        $this->assertSame(['online', 'booked', '2026-11-14 08:30'], [$a->source, $a->status, $a->starts_at->format('Y-m-d H:i')]);
        $p = Patient::sole();
        $this->assertSame(['Mariam Karimi', '0799111222', true], [$p->name, $p->phone, $p->registered_online]);
        $this->get('/book/'.$booking->token)->assertOk()->assertSee('Your appointment')->assertSee('08:30');

        // the desk sees it
        $this->actingAs(\App\Models\User::where('email', 'owner@acme.test')->sole());
        $this->get('/appointments?date=2026-11-14')->assertSee('Booked online');
        $this->get('/')->assertSee('Booked online, to confirm');
        auth()->logout();

        // she books again, the same person: no second record; then cancels the second online
        $this->ask($d, '09:00');
        $second = OnlineBooking::latest('id')->first();
        $this->post('/book/'.$second->token.'/code', ['code' => $this->code]);
        $this->assertSame(1, Patient::count());
        $this->post('/book/'.$second->token.'/cancel')->assertSessionHas('success');
        $this->assertSame('cancelled', $second->appointment->fresh()->status);
    }

    public function test_the_page_tells_nothing_of_other_patients_and_stops_abuse(): void
    {
        $d = $this->clinicOpen();
        // the desk booked 08:30 for someone else: the time is not offered, and asking for it says only that it is taken
        Appointment::create(['patient_id' => Patient::create(['number' => 'P-9', 'name' => 'Secret Person', 'gender' => 'male'])->id, 'doctor_id' => $d->id, 'branch_id' => 1,
            'starts_at' => '2026-11-14 08:30', 'ends_at' => '2026-11-14 08:45', 'status' => 'booked', 'source' => 'desk']);
        $this->get('/book?doctor='.$d->id.'&date=2026-11-14')->assertDontSee('for="t-0830"', false)->assertDontSee('Secret Person');
        $this->ask($d, '08:30')->assertSessionHas('danger', 'That time is not free any more. Please choose another one.');
        $this->ask($d, '11:00')->assertSessionHas('danger');                                    // not his hours
        $this->post('/book', ['website' => 'http://spam'] + ['doctor_id' => $d->id])->assertStatus(422);  // the hidden box

        // too many wrong codes
        $this->ask($d, '08:00');
        $b = OnlineBooking::latest('id')->first();
        foreach (range(1, 5) as $i) {
            $this->post('/book/'.$b->token.'/code', ['code' => '111111']);
        }
        $this->post('/book/'.$b->token.'/code', ['code' => $this->code])->assertSessionHas('danger', 'Too many wrong codes. Please book again.');
        $this->assertSame(1, Appointment::count());

        // a doctor closed to online booking, and a clinic that did not switch it on
        $d->update(['books_online' => false]);
        $this->get('/book')->assertSee('No doctor can be booked online now');
        Setting::updateOrCreate(['key' => 'online_booking'], ['value' => '0']);
        app()->forgetInstance('erp.settings');
        $this->get('/book')->assertOk()->assertSee('Booking online is not open now');
    }
}
