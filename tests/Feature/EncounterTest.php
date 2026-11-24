<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Central\Icd10Code;
use App\Models\Doctor;
use App\Models\Encounter;
use App\Models\History\Problem;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The visit: opened from the queue, written, diagnosed, signed and locked; afterwards only addenda. */
class EncounterTest extends TestCase
{
    use RefreshDatabase;

    private function setUpDay(): array
    {
        Icd10Code::insert([
            ['code' => 'J06.9', 'description' => 'Acute upper respiratory infection, unspecified', 'billable' => true],
            ['code' => 'I10', 'description' => 'Essential (primary) hypertension', 'billable' => true],
        ]);
        $this->clinic();
        $this->owner();
        $this->post('/doctors', ['name' => 'Dr. Ahmad', 'email' => 'ahmad@acme.test', 'password' => 'doctor-pass-1', 'fee' => '300', 'colour' => 'teal', 'is_active' => '1']);
        $this->post('/patients', ['name' => 'Gul Ahmad', 'gender' => 'male', 'age' => '50', 'allergies' => 'Penicillin']);
        $patient = Patient::sole();
        $doctor = Doctor::sole();
        $this->post('/appointments', ['walk_in' => '1', 'patient_id' => $patient->id, 'doctor_id' => $doctor->id, 'reason' => 'Cough and fever']);

        $user = User::where('email', 'ahmad@acme.test')->sole();
        $this->actingAs($user);

        return [$doctor, $patient, Appointment::sole(), $user];
    }

    public function test_the_doctor_writes_and_signs_a_visit_that_is_then_locked(): void
    {
        [$doctor, $patient, $appointment] = $this->setUpDay();

        // from the queue: call in, open the visit (only once), the complaint is already there
        $this->post('/queue/'.$doctor->id.'/next');
        $this->get('/queue')->assertOk()->assertSee('Open the visit');
        $this->post('/visits', ['appointment_id' => $appointment->id])->assertRedirect();
        $this->post('/visits', ['appointment_id' => $appointment->id])->assertRedirect();
        $encounter = Encounter::sole();
        $this->assertSame(['draft', 'Cough and fever', $doctor->id], [$encounter->status, $encounter->complaint, $encounter->doctor_id]);

        $this->get('/visits/'.$encounter->id)->assertOk()->assertSee('Note of the visit')->assertSee('Penicillin')->assertSee('Save and sign');
        $this->put('/visits/'.$encounter->id, ['complaint' => 'Cough and fever for 3 days', 'examination' => 'Throat red, chest clear', 'plan' => 'Paracetamol, fluids', 'follow_up_on' => '2026-11-20'])
            ->assertRedirect('/visits/'.$encounter->id);
        // diagnoses: the first is the main one; a long-standing one goes on the problem list too (once)
        $this->post('/visits/'.$encounter->id.'/diagnoses', ['description' => 'Acute upper respiratory infection, unspecified', 'icd10' => 'J06.9']);
        $this->post('/visits/'.$encounter->id.'/diagnoses', ['description' => 'Essential (primary) hypertension', 'icd10' => 'I10', 'to_problems' => '1']);
        $this->post('/visits/'.$encounter->id.'/diagnoses', ['description' => 'Essential (primary) hypertension', 'icd10' => 'I10', 'to_problems' => '1']);
        $this->assertSame([true, false, false], $encounter->diagnoses()->get()->pluck('is_primary')->all());
        $this->assertSame(1, Problem::where('icd10', 'I10')->count());
        $this->delete('/visits/'.$encounter->id.'/diagnoses/'.$encounter->diagnoses()->where('is_primary', false)->latest('id')->value('id'));
        $this->assertSame(2, $encounter->diagnoses()->count());
        // vital signs of the visit
        $this->post('/patients/'.$patient->id.'/vitals', ['encounter_id' => $encounter->id, 'temperature' => '38.4', 'pulse' => '96'])->assertRedirect('/visits/'.$encounter->id);

        // a wrong password signs nothing
        $this->from('/visits/'.$encounter->id)->post('/visits/'.$encounter->id.'/sign', ['password' => 'wrong'])->assertSessionHas('danger');
        $this->assertSame('draft', $encounter->fresh()->status);
        // signed: what was in the boxes is signed with it, and the appointment is done
        $this->post('/visits/'.$encounter->id.'/sign', ['password' => 'doctor-pass-1', 'assessment' => 'Viral infection'])->assertSessionHas('success');
        $encounter->refresh();
        $this->assertSame(['signed', 'Viral infection'], [$encounter->status, $encounter->assessment]);
        $this->assertTrue($encounter->signatureHolds());
        $this->assertSame(Appointment::COMPLETED, $appointment->fresh()->status);

        // locked: nothing changes it, only an addendum is added
        $this->from('/visits/'.$encounter->id)->put('/visits/'.$encounter->id, ['plan' => 'Something else'])->assertSessionHas('danger');
        $this->from('/visits/'.$encounter->id)->post('/visits/'.$encounter->id.'/diagnoses', ['description' => 'X'])->assertSessionHas('danger');
        $this->assertSame('Paracetamol, fluids', $encounter->fresh()->plan);
        $this->post('/visits/'.$encounter->id.'/addenda', ['body' => 'Throat swab negative.'])->assertSessionHas('success');
        $this->get('/visits/'.$encounter->id)->assertOk()->assertSee('Throat swab negative.')->assertSee('Viral infection')->assertSee('J06.9')->assertDontSee('Save and sign');

        // a change made behind the system's back is seen
        \Illuminate\Support\Facades\DB::table('encounters')->where('id', $encounter->id)->update(['plan' => 'Antibiotics']);
        $this->assertFalse($encounter->fresh()->signatureHolds());
        $this->get('/visits/'.$encounter->id)->assertOk()->assertSee('changed after it was signed');

        // the visit is on the patient's chart
        $this->get('/patients/'.$patient->id.'?tab=visits')->assertOk()->assertSee('Signed')->assertSee('Acute upper respiratory infection');
        $this->get('/patients/'.$patient->id.'?tab=summary')->assertOk()->assertSee('Essential (primary) hypertension');
    }

    public function test_only_the_doctor_of_the_visit_writes_it_and_an_empty_visit_is_not_signed(): void
    {
        [$doctor, $patient, $appointment, $ahmad] = $this->setUpDay();
        $this->post('/visits', ['patient_id' => $patient->id])->assertRedirect();
        $encounter = Encounter::sole();
        $this->from('/visits/'.$encounter->id)->post('/visits/'.$encounter->id.'/sign', ['password' => 'doctor-pass-1', 'complaint' => ''])->assertSessionHas('danger');

        // another doctor reads it but does not write it
        $this->owner();
        $this->post('/doctors', ['name' => 'Dr. Sima', 'email' => 'sima@acme.test', 'password' => 'doctor-pass-2', 'fee' => '300', 'colour' => 'rose', 'is_active' => '1']);
        $this->actingAs(User::where('email', 'sima@acme.test')->sole());
        $this->get('/visits/'.$encounter->id)->assertOk()->assertDontSee('Save and sign');
        $this->put('/visits/'.$encounter->id, ['plan' => 'x'])->assertForbidden();

        // a nurse cannot open a visit; the front desk cannot read one
        $this->userWith(['patients.view', 'clinical.view', 'clinical.manage']);
        $this->from('/patients/'.$patient->id)->post('/visits', ['patient_id' => $patient->id])->assertSessionHas('danger');
        $this->userWith(['patients.view', 'appointments.manage']);
        $this->get('/visits/'.$encounter->id)->assertForbidden();
    }
}
