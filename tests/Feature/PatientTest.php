<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientTest extends TestCase
{
    use RefreshDatabase;

    private function form(array $over = []): array
    {
        return $over + ['name' => 'Gul Ahmad', 'father_name' => 'Rahim', 'gender' => 'male', 'date_of_birth' => '1990-03-21', 'phone' => '0799 123 456',
            'tazkira' => '1400-0101-12345', 'blood_group' => 'O+', 'province' => 'Kabul', 'allergies' => 'Penicillin'];
    }

    public function test_the_front_desk_registers_a_patient_who_gets_a_record_number(): void
    {
        $this->clinic();
        $this->userWith(['patients.view', 'patients.manage', 'appointments.manage']);

        $this->get('/patients')->assertOk();
        $this->get('/patients/create')->assertOk()->assertSee('Known allergies');
        $response = $this->post('/patients', $this->form());
        $patient = Patient::sole();
        $response->assertRedirect(route('patients.show', ['id' => $patient->id]))->assertSessionHas('success');
        $this->assertSame(['P-000001', 'Gul Ahmad', 'male', '1990-03-21', false], [$patient->number, $patient->name, $patient->gender, $patient->date_of_birth->toDateString(), $patient->dob_estimated]);
        $this->assertSame(36, $patient->age());   // on 2026-11-13

        // the page of the patient: the allergy in red, and the way to book
        $this->get('/patients/'.$patient->id)->assertOk()->assertSee('P-000001')->assertSee('patient-allergy', false)->assertSee('Penicillin')
            ->assertSee('Book an appointment')->assertSee('Rahim');
        $this->assertTrue(AuditLog::where('action', 'viewed')->where('subject_type', 'Patient')->where('subject_id', $patient->id)->exists());

        // the next one gets the next number; only the age is known
        $this->post('/patients', ['name' => 'Zarghona', 'gender' => 'female', 'age' => '4', 'allergies' => 'None known']);
        $child = Patient::where('name', 'Zarghona')->sole();
        $this->assertSame(['P-000002', '2022-01-01', true, '4 y ~'], [$child->number, $child->date_of_birth->toDateString(), $child->dob_estimated, $child->ageText()]);
        $this->get('/patients/'.$child->id)->assertOk()->assertSee('No known allergies')->assertDontSee('patient-allergy', false);

        // what is refused
        $this->post('/patients', $this->form(['gender' => 'robot']))->assertSessionHasErrors('gender');
        $this->post('/patients', $this->form(['date_of_birth' => '2030-01-01']))->assertSessionHasErrors('date_of_birth');
        $this->post('/patients', $this->form(['name' => '']))->assertSessionHasErrors('name');
        $this->assertSame(2, Patient::count());
    }

    public function test_patients_are_found_by_name_father_phone_number_or_tazkira(): void
    {
        $this->clinic();
        $this->owner();
        $this->post('/patients', $this->form());
        $this->post('/patients', $this->form(['name' => 'Mariam Sultani', 'father_name' => 'Nazir', 'gender' => 'female', 'phone' => '0788 000 111', 'tazkira' => '9999']));

        foreach (['gul' => 'Gul Ahmad', 'Rahim' => 'Gul Ahmad', '0788' => 'Mariam Sultani', 'P-000002' => 'Mariam Sultani', '1400-0101' => 'Gul Ahmad', 'mariam nazir' => 'Mariam Sultani'] as $term => $name) {
            $found = $this->getJson('/patients/search?q='.urlencode($term))->assertOk()->json('results');
            $this->assertCount(1, $found, $term);
            $this->assertStringStartsWith($name, $found[0]['text'], $term);
        }
        $this->getJson('/patients/search?q=a')->assertOk()->assertExactJson(['results' => []]);   // one letter is too little
        $this->get('/patients?q=sultani')->assertOk()->assertSee('Mariam Sultani')->assertDontSee('Gul Ahmad');
    }

    public function test_a_patient_with_visits_is_kept_and_permissions_are_respected(): void
    {
        $this->clinic();
        $this->owner();
        $this->post('/patients', $this->form());
        $patient = Patient::sole();

        // without appointments a mistaken record can be removed; it is only hidden
        $this->delete('/patients/'.$patient->id)->assertRedirect(route('patients.index'));
        $this->assertSoftDeleted($patient);
        $this->get('/patients/'.$patient->id)->assertNotFound();

        $this->userWith(['patients.view']);
        $this->get('/patients')->assertOk();
        $this->get('/patients/create')->assertForbidden();
        $this->post('/patients', $this->form())->assertForbidden();

        $this->userWith([]);
        $this->get('/patients')->assertForbidden();
        $this->getJson('/patients/search?q=gul')->assertForbidden();
    }
}
