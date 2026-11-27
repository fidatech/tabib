<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Encounter;
use App\Models\Patient;
use App\Models\SmsMessage;
use App\Models\User;
use App\Services\Sms;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** The check-up the doctor asks for: a reminder some days before, the list for the desk. (The clock stands at 2026-11-13 10:00.) */
class FollowUpTest extends TestCase
{
    use RefreshDatabase;

    private function visitWithCheckUp(string $comeBack, array $patient = []): Encounter
    {
        $this->clinic();
        $this->owner();
        $this->post('/doctors', ['name' => 'Dr. Ahmad', 'email' => 'ahmad@acme.test', 'password' => 'doctor-pass-1', 'fee' => '300', 'colour' => 'teal', 'is_active' => '1']);
        $this->post('/patients', $patient + ['name' => 'Gul Ahmad', 'gender' => 'male', 'age' => '40', 'phone' => '0700123456']);
        Http::fake(['sms.example.af/*' => Http::response('OK')]);
        $this->put('/settings/sms', ['sms_enabled' => '1', 'sms_gateway' => 'http', 'sms_country_code' => '93', 'sms_http_url' => 'https://sms.example.af/send?to={to}&text={message}',
            'sms_http_method' => 'get', 'sms_language' => 'en', 'sms_reminder_time' => '16:00', 'sms_followups' => '1', 'sms_followup_days' => '2'])->assertSessionHas('success');
        $this->actingAs(User::where('email', 'ahmad@acme.test')->sole());
        $this->post('/visits', ['patient_id' => Patient::sole()->id]);
        $visit = Encounter::sole();
        $this->put('/visits/'.$visit->id, ['complaint' => 'Cough', 'follow_up_on' => $comeBack]);
        $this->post('/visits/'.$visit->id.'/sign', ['password' => 'doctor-pass-1', 'follow_up_on' => $comeBack])->assertSessionHas('success');

        return $visit->fresh();
    }

    public function test_the_patient_is_reminded_once_some_days_before_the_check_up(): void
    {
        $visit = $this->visitWithCheckUp('2026-11-20');
        $this->assertSame('2026-11-20', $visit->follow_up_on->toDateString());

        $this->assertSame(0, Sms::followUps(Carbon::parse('2026-11-17 17:00')));   // 3 days before: not yet
        $this->assertSame(0, Sms::followUps(Carbon::parse('2026-11-18 15:00')));   // the day, but before the hour
        $this->assertSame(1, Sms::followUps(Carbon::parse('2026-11-18 16:30')));
        $this->assertSame(0, Sms::followUps(Carbon::parse('2026-11-19 16:30')));   // once
        $sms = SmsMessage::where('kind', 'followup')->sole();
        $this->assertStringContainsString('come back for a check-up on 2026-11-20', $sms->body);
        $this->assertStringContainsString('Dr. Ahmad', $sms->body);

        // the desk sees who to call
        $this->get('/check-ups')->assertOk()->assertSee('Gul Ahmad')->assertSee('To book')->assertSee('Reminded on');
    }

    public function test_no_reminder_when_an_appointment_is_already_booked(): void
    {
        $visit = $this->visitWithCheckUp('2026-11-21');
        $this->actingAs(User::where('email', 'owner@acme.test')->sole());
        $this->post('/appointments', ['patient_id' => $visit->patient_id, 'doctor_id' => $visit->doctor_id, 'date' => '2026-11-22', 'time' => '09:00', 'minutes' => '15'])->assertSessionHas('success');
        $this->assertSame(0, Sms::followUps(Carbon::parse('2026-11-19 16:30')));
        $this->assertSame(0, SmsMessage::where('kind', 'followup')->count());
        $this->get('/check-ups')->assertOk()->assertSee('No check-up to book.');
        $this->get('/check-ups?show=all')->assertOk()->assertSee('Has an appointment');
        $this->assertSame(1, Appointment::count());
    }

    public function test_the_patient_who_wants_no_messages_is_not_texted_but_still_listed(): void
    {
        $this->visitWithCheckUp('2026-11-15', ['no_sms' => '1']);
        $this->assertSame(0, Sms::followUps(Carbon::parse('2026-11-13 16:30')));
        $this->assertSame(0, SmsMessage::where('kind', 'followup')->count());
        $this->get('/check-ups')->assertOk()->assertSee('Gul Ahmad');
    }
}
