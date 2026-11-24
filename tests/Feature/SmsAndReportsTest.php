<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\SmsMessage;
use App\Services\Sms;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Text reminders of appointments through the gateway the clinic chose, and the reports. (The clock stands at 2026-11-13 10:00.) */
class SmsAndReportsTest extends TestCase
{
    use RefreshDatabase;

    private function setUpClinic(): array
    {
        $this->clinic();
        $this->owner();
        $this->post('/doctors', ['name' => 'Dr. Ahmad', 'email' => 'ahmad@acme.test', 'password' => 'doctor-pass-1', 'fee' => '300', 'colour' => 'teal', 'is_active' => '1']);
        $this->post('/patients', ['name' => 'Gul Ahmad', 'gender' => 'male', 'age' => '40', 'phone' => '0700 123 456']);
        $this->post('/patients', ['name' => 'Mariam', 'gender' => 'female', 'age' => '30', 'phone' => '0799 000 111', 'no_sms' => '1']);

        return [Doctor::sole(), Patient::where('name', 'Gul Ahmad')->sole(), Patient::where('name', 'Mariam')->sole()];
    }

    private function settings(array $values = []): void
    {
        $this->put('/settings/sms', $values + ['sms_enabled' => '1', 'sms_gateway' => 'http', 'sms_country_code' => '93', 'sms_sender' => 'ACME',
            'sms_http_url' => 'https://sms.example.af/send?to={to}&text={message}&from={sender}', 'sms_http_method' => 'get', 'sms_http_token' => 'secret-token',
            'sms_language' => 'en', 'sms_reminders' => '1', 'sms_reminder_time' => '16:00', 'sms_on_booking' => '1'])->assertSessionHas('success');
    }

    public function test_numbers_are_written_for_the_gateway(): void
    {
        $this->clinic();
        $this->assertSame('+93700123456', Sms::number('0700 123 456'));
        $this->assertSame('+93700123456', Sms::number('۰۷۰۰۱۲۳۴۵۶'));
        $this->assertSame('+923001234567', Sms::number('+92 300 1234567'));
        $this->assertSame('+93700123456', Sms::number('700123456'));
        $this->assertNull(Sms::number('123'));
    }

    public function test_bookings_and_reminders_are_texted_once_and_failures_are_kept(): void
    {
        [$doctor, $gul, $mariam] = $this->setUpClinic();
        Http::fake(['sms.example.af/*' => Http::response('OK', 200)]);
        $this->settings();
        $this->assertNotSame('secret-token', \App\Models\Setting::find('sms_http_token')->value);   // kept encrypted

        // a booking sends its message; the patient who wants none gets none
        $this->post('/appointments', ['patient_id' => $gul->id, 'doctor_id' => $doctor->id, 'date' => '2026-11-14', 'time' => '09:00', 'minutes' => '15']);
        $this->post('/appointments', ['patient_id' => $mariam->id, 'doctor_id' => $doctor->id, 'date' => '2026-11-14', 'time' => '09:30', 'minutes' => '15']);
        $this->assertSame(1, SmsMessage::count());
        $sent = SmsMessage::sole();
        $this->assertSame(['booking', 'sent', '+93700123456'], [$sent->kind, $sent->status, $sent->to]);
        $this->assertStringContainsString('Dr. Ahmad', $sent->body);
        $this->assertStringContainsString('2026-11-14 at 09:00', $sent->body);
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'to=%2B93700123456') && str_contains($r->url(), 'from=ACME') && $r->hasHeader('Authorization', 'Bearer secret-token'));

        // before the hour of the reminders nothing; after it, once
        $this->assertSame(0, Sms::remind(Carbon::parse('2026-11-13 15:00')));
        $this->assertSame(1, Sms::remind(Carbon::parse('2026-11-13 16:05')));
        $this->assertSame(0, Sms::remind(Carbon::parse('2026-11-13 17:00')));
        $this->assertSame(1, SmsMessage::where('kind', 'reminder')->count());
        $this->assertNotNull(Appointment::where('patient_id', $gul->id)->sole()->reminded_at);

        // a gateway that refuses: the message is kept as failed, the desk goes on
        Http::fake(['sms.example.af/*' => Http::response('no credit', 402)]);
        $this->post('/settings/sms/test', ['to' => '0700123456'])->assertSessionHas('danger');
        $this->assertSame('failed', SmsMessage::latest('id')->first()->status);
        $this->get('/settings/sms')->assertOk()->assertSee('no credit')->assertSee('Failed');

        // the command walks the clinics
        $this->artisan('sms:reminders')->assertSuccessful();
    }

    public function test_twilio_and_the_log_only_gateway(): void
    {
        [$doctor, $gul] = $this->setUpClinic();
        Http::fake(['api.twilio.com/*' => Http::response(['sid' => 'SM123'], 201)]);
        $this->settings(['sms_gateway' => 'twilio', 'sms_twilio_sid' => 'AC1', 'sms_twilio_token' => 'tok', 'sms_sender' => '+15005550006', 'sms_http_url' => '']);
        $this->post('/settings/sms/test', ['to' => '0700123456'])->assertSessionHas('success');
        $this->assertSame(['sent', 'SM123'], [SmsMessage::sole()->status, SmsMessage::sole()->provider_id]);
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'Accounts/AC1/Messages.json') && $r['To'] === '+93700123456');

        // the words in Dari; nothing leaves the clinic with the log-only gateway
        $this->settings(['sms_gateway' => 'log', 'sms_language' => 'fa']);
        $this->post('/appointments', ['patient_id' => $gul->id, 'doctor_id' => $doctor->id, 'date' => '2026-11-14', 'time' => '09:00', 'minutes' => '15']);
        $logged = SmsMessage::latest('id')->first();
        $this->assertSame('logged', $logged->status);
        $this->assertStringContainsString('محترم', $logged->body);
        Http::assertSentCount(1);
    }

    public function test_the_reports_add_up(): void
    {
        [$doctor, $gul, $mariam] = $this->setUpClinic();
        $this->post('/appointments', ['walk_in' => '1', 'patient_id' => $gul->id, 'doctor_id' => $doctor->id]);
        $this->post('/appointments', ['patient_id' => $mariam->id, 'doctor_id' => $doctor->id, 'date' => '2026-11-13', 'time' => '11:00', 'minutes' => '15']);
        $a = Appointment::where('patient_id', $mariam->id)->sole();
        $this->post('/appointments/'.$a->id.'/no_show');
        $this->post('/invoices', ['appointment_id' => Appointment::where('patient_id', $gul->id)->sole()->id]);
        $this->post('/invoices/'.Invoice::sole()->id.'/pay', ['amount' => '100', 'method' => 'cash']);

        $this->get('/reports')->assertOk()->assertSee('Income')->assertSee('Diagnoses');
        $this->get('/reports/income?from=2026-11-01&to=2026-11-30')->assertOk()->assertSee('300.00')->assertSee('100.00')->assertSee('200.00')->assertSee('Consultation');
        $this->get('/reports/appointments')->assertOk()->assertSee('Dr. Ahmad')->assertSee('50%');
        $this->get('/reports/owed')->assertOk()->assertSee('Gul Ahmad')->assertSee('200.00');
        $this->get('/reports/diagnoses')->assertOk();
        $this->get('/reports/medicines')->assertOk();
        $this->get('/reports/income?export=xlsx')->assertOk()->assertHeader('Content-Disposition');
        $this->get('/reports/nothing')->assertNotFound();

        // the front desk has no reports
        $this->actingAs($this->userWith(['patients.view', 'billing.view']));
        $this->get('/reports')->assertForbidden();
    }
}
