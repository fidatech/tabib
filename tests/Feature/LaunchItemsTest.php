<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Encounter;
use App\Models\Invoice;
use App\Models\NoteTemplate;
use App\Models\Patient;
use App\Models\SmsMessage;
use App\Models\User;
use App\Models\Vital;
use App\Services\Sms;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Note templates with the tooth chart and the pregnancy form, merging two records of one person, and the limits of a plan. */
class LaunchItemsTest extends TestCase
{
    use RefreshDatabase;

    private function visit(array $patient = []): array
    {
        $this->clinic();
        $this->owner();
        $this->post('/doctors', ['name' => 'Dr. Ahmad', 'email' => 'ahmad@acme.test', 'password' => 'doctor-pass-1', 'fee' => '300', 'colour' => 'teal', 'is_active' => '1']);
        $this->post('/patients', $patient + ['name' => 'Gul Ahmad', 'gender' => 'male', 'age' => '40', 'phone' => '0700123456']);
        $this->actingAs(User::where('email', 'ahmad@acme.test')->sole());
        $this->post('/visits', ['patient_id' => Patient::sole()->id]);

        return [Encounter::sole(), Patient::sole()];
    }

    public function test_every_clinic_gets_templates_and_the_dental_one_brings_the_tooth_chart_signed_with_the_note(): void
    {
        [$visit] = $this->visit();
        $this->assertSame(5, NoteTemplate::count());
        $dental = NoteTemplate::where('kind', 'dental')->sole();

        $this->put('/visits/'.$visit->id, ['complaint' => 'Toothache']);
        $this->post('/visits/'.$visit->id.'/template', ['template_id' => $dental->id])->assertSessionHas('success');
        $visit->refresh();
        $this->assertSame(['dental', 'Toothache'], [$visit->kind, $visit->complaint]);   // what was written stays
        $this->assertStringContainsString('Intra-oral', $visit->examination);
        $this->get('/visits/'.$visit->id)->assertOk()->assertSee('Tooth chart')->assertSee('tooth-36', false);

        $this->put('/visits/'.$visit->id, ['complaint' => 'Toothache', 'extra' => ['teeth' => ['36' => 'caries', '11' => '', '18' => 'missing'], 'teeth_note' => 'Deep caries 36']]);
        $this->assertSame(['teeth' => ['18' => 'missing', '36' => 'caries'], 'teeth_note' => 'Deep caries 36'], $visit->fresh()->extra);
        $this->from('/visits/'.$visit->id)->put('/visits/'.$visit->id, ['extra' => ['teeth' => ['36' => 'broken']]])->assertSessionHasErrors();

        $this->post('/visits/'.$visit->id.'/sign', ['password' => 'doctor-pass-1'])->assertSessionHas('success');
        $visit->refresh();
        $this->assertTrue($visit->signatureHolds());
        $this->get('/visits/'.$visit->id)->assertOk()->assertSee('tooth-caries', false)->assertSee('Deep caries 36');
        // the chart is under the signature
        Encounter::whereKey($visit->id)->update(['extra' => json_encode(['teeth' => ['36' => 'filled']])]);
        $this->assertFalse($visit->fresh()->signatureHolds());
    }

    public function test_the_antenatal_form_works_out_the_due_date(): void
    {
        [$visit] = $this->visit(['name' => 'Mariam', 'gender' => 'female', 'age' => '25']);
        $this->post('/visits/'.$visit->id.'/template', ['template_id' => NoteTemplate::where('kind', 'antenatal')->sole()->id]);
        // the clock stands at 2026-11-13: a period that began on 2026-08-01 is 14 weeks 6 days ago; due on 2027-05-08
        $this->put('/visits/'.$visit->id, ['extra' => ['lmp' => '2026-08-01', 'gravida' => '2', 'para' => '1', 'fetal_heart' => '148', 'presentation' => 'cephalic']]);
        $this->assertSame(['edd' => '2027-05-08', 'weeks' => 14, 'days' => 6], array_intersect_key(\App\Support\Forms::pregnancy('2026-08-01', $visit->fresh()->started_at), array_flip(['weeks', 'days', 'edd'])));
        $this->get('/visits/'.$visit->id)->assertOk()->assertSee('14 weeks 6 days pregnant');
    }

    public function test_two_records_of_one_person_are_merged_and_both_histories_kept(): void
    {
        $this->clinic();
        $this->owner();
        $this->post('/doctors', ['name' => 'Dr. Ahmad', 'email' => 'ahmad@acme.test', 'password' => 'doctor-pass-1', 'fee' => '300', 'colour' => 'teal', 'is_active' => '1']);
        $this->post('/patients', ['name' => 'Gul Ahmad', 'father_name' => 'Karim', 'gender' => 'male', 'age' => '40', 'phone' => '0700123456']);
        $this->post('/patients', ['name' => 'Gul Ahmad', 'father_name' => 'Karim', 'gender' => 'male', 'age' => '40', 'phone' => '0700123456', 'tazkira' => '1400-0101-12345', 'allergies' => 'Penicillin']);
        [$keep, $dup] = Patient::orderBy('id')->get()->all();
        $this->post('/appointments', ['walk_in' => '1', 'patient_id' => $dup->id, 'doctor_id' => Doctor::sole()->id]);
        $this->post('/invoices', ['appointment_id' => Appointment::sole()->id]);
        Vital::create(['patient_id' => $dup->id, 'taken_at' => now(), 'weight' => 70]);

        $this->get('/patients/'.$keep->id.'/merge')->assertOk()->assertSee($dup->number);   // found as looking alike
        $this->get('/patients/'.$keep->id.'/merge?duplicate='.$dup->id)->assertOk()->assertSee('Duplicate (closed)');
        $this->from('/patients/'.$keep->id.'/merge')->post('/patients/'.$keep->id.'/merge', ['duplicate_id' => $dup->id, 'reason' => 'Registered twice', 'password' => 'wrong'])->assertSessionHas('danger');
        $this->post('/patients/'.$keep->id.'/merge', ['duplicate_id' => $dup->id, 'reason' => 'Registered twice', 'password' => 'password123'])->assertSessionHas('success');

        $this->assertSame([$keep->id], Appointment::pluck('patient_id')->unique()->values()->all());
        $this->assertSame($keep->id, Invoice::sole()->patient_id);
        $this->assertSame($keep->id, Vital::sole()->patient_id);
        $this->assertSame(1, \App\Models\History\Allergy::where('patient_id', $keep->id)->count());
        $this->assertSame('1400-0101-12345', $keep->fresh()->tazkira);   // what the kept record lacked
        $closed = Patient::onlyTrashed()->sole();
        $this->assertSame($keep->id, $closed->merged_into_id);
        $this->get('/patients/'.$dup->id)->assertRedirect('/patients/'.$keep->id);
        $this->assertSame(1, Patient::count());
    }

    public function test_the_plan_limits_doctors_and_text_messages_and_the_panel_shows_the_use(): void
    {
        $tenant = $this->clinic();
        $tenant->update(['max_doctors' => 1, 'max_sms_month' => 1]);
        $this->owner();
        $this->post('/doctors', ['name' => 'Dr. Ahmad', 'email' => 'ahmad@acme.test', 'password' => 'doctor-pass-1', 'fee' => '300', 'colour' => 'teal', 'is_active' => '1']);
        $this->from('/doctors/create')->post('/doctors', ['name' => 'Dr. Zahra', 'email' => 'zahra@acme.test', 'password' => 'doctor-pass-2', 'fee' => '300', 'colour' => 'teal', 'is_active' => '1'])
            ->assertSessionHas('danger');
        $this->assertSame(1, Doctor::count());

        Http::fake(['sms.example.af/*' => Http::response('OK')]);
        foreach (['sms_enabled' => '1', 'sms_gateway' => 'http', 'sms_http_url' => 'https://sms.example.af/send?to={to}&text={message}'] as $k => $v) {
            \App\Models\Setting::updateOrCreate(['key' => $k], ['value' => $v]);
        }
        app()->forgetInstance('erp.settings');
        $this->assertSame('sent', Sms::send('+93700123456', 'One', 'test')->status);
        $second = Sms::send('+93700123456', 'Two', 'test');
        $this->assertSame('failed', $second->status);
        $this->assertStringContainsString('used up', $second->error);
        Http::assertSentCount(1);

        $this->central();
        $this->systemAdmin();
        $this->get('/admin/tenants/'.$tenant->id)->assertOk()->assertSee('Visits this month')->assertSee('1 / 1');
        $this->put('/admin/tenants/'.$tenant->id, ['name' => 'Acme Clinic', 'max_doctors' => 3, 'max_sms_month' => 500])->assertRedirect();
        $this->assertSame([3, 500], [$tenant->fresh()->max_doctors, $tenant->fresh()->max_sms_month]);
    }

    public function test_the_paper_of_a_prescription_draws_the_day_for_who_cannot_read(): void
    {
        [$visit, $patient] = $this->visit();
        $this->post('/prescriptions', ['patient_id' => $patient->id, 'encounter_id' => $visit->id]);
        $rx = \App\Models\Prescription::sole();
        $para = \App\Models\Medicine::where('code', 'paracetamol-500-mg-tablet')->sole();
        $this->post('/prescriptions/'.$rx->id.'/items', ['medicine_id' => $para->id, 'name' => 'x', 'dose_amount' => '1', 'dose_unit' => 'tablet', 'frequency' => 'tds', 'timing' => 'after_food', 'days' => '3']);
        $this->post('/prescriptions/'.$rx->id.'/sign', ['password' => 'doctor-pass-1']);
        $page = $this->get('/prescriptions/'.$rx->id.'/print?lang=fa')->assertOk()->getContent();
        $this->assertSame(3, substr_count($page, 'class="slot on"'));
        $this->assertStringContainsString('slot food', $page);
    }
}
