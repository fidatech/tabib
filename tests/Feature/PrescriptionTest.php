<?php

namespace Tests\Feature;

use App\Models\Doctor;
use App\Models\History\Medication;
use App\Models\Medicine;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\User;
use App\Services\Prescribing;
use App\Support\Qr;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Prescriptions: written from the list of medicines, checked, signed with the password, printed with a QR code, cancelled. */
class PrescriptionTest extends TestCase
{
    use RefreshDatabase;

    private function setUpDoctor(array $patient = []): array
    {
        $this->clinic();
        $this->owner();
        $this->post('/doctors', ['name' => 'Dr. Ahmad', 'email' => 'ahmad@acme.test', 'password' => 'doctor-pass-1', 'fee' => '300', 'colour' => 'teal', 'is_active' => '1', 'licence_no' => 'AMC-1234']);
        $this->post('/patients', $patient + ['name' => 'Gul Ahmad', 'gender' => 'male', 'age' => '50', 'allergies' => 'Penicillin']);
        $user = User::where('email', 'ahmad@acme.test')->sole();
        $this->actingAs($user);

        return [Doctor::sole(), Patient::sole(), $user];
    }

    private function med(string $code): Medicine
    {
        return Medicine::where('code', $code)->sole();
    }

    public function test_every_clinic_starts_with_the_list_of_medicines_and_finds_them_by_brand(): void
    {
        $this->setUpDoctor();
        $this->assertGreaterThan(500, Medicine::count());
        $this->getJson('/medicines/search?q=Panadol')->assertOk()->assertJsonFragment(['name' => 'Paracetamol']);
        $this->getJson('/medicines/search?q=Augmentin')->assertOk()->assertJsonFragment(['name' => 'Amoxicillin + clavulanic acid']);

        // a new version adds medicines but keeps what the clinic wrote
        $para = $this->med('paracetamol-500-mg-tablet');
        $para->update(['max_daily_mg' => 4000, 'is_active' => false]);
        $para->delete();
        $this->assertSame(1, Prescribing::syncMedicines());
        $this->assertSame(0, Prescribing::syncMedicines());
        $this->med('paracetamol-500-mg-tablet');
        $this->med('ibuprofen-400-mg-tablet')->update(['max_daily_mg' => 2400, 'is_active' => false]);
        Prescribing::syncMedicines();
        $this->assertSame([2400.0, false], [$this->med('ibuprofen-400-mg-tablet')->max_daily_mg, $this->med('ibuprofen-400-mg-tablet')->is_active]);

        // the clinic adds its own medicine and it is found
        $this->post('/medicines', ['name' => 'Joshanda', 'form' => 'sachet', 'brands' => 'Qarshi Joshanda', 'is_active' => '1'])->assertRedirect();
        $this->getJson('/medicines/search?q=Qarshi')->assertJsonFragment(['name' => 'Joshanda']);
    }

    public function test_the_doctor_writes_checks_signs_prints_and_cancels(): void
    {
        [$doctor, $patient] = $this->setUpDoctor();
        $this->post('/prescriptions', ['patient_id' => $patient->id])->assertRedirect();
        $this->post('/prescriptions', ['patient_id' => $patient->id]);
        $rx = Prescription::sole();

        $para = $this->med('paracetamol-500-mg-tablet');
        $this->post('/prescriptions/'.$rx->id.'/items', ['medicine_id' => $para->id, 'name' => 'x', 'dose_amount' => '2', 'dose_unit' => 'tablet', 'frequency' => 'tds', 'timing' => 'after_food', 'days' => '5'])->assertSessionHas('success');
        $item = $rx->items()->sole();
        $this->assertSame(['Paracetamol', '500 mg', '30 tablets'], [$item->name, $item->strength, $item->quantity]);
        $this->assertSame('2 tablets, 3 times a day, after food, for 5 days', $item->directions('en'));
        $this->assertSame('۲ تابلیت، روزانه سه بار، بعد از غذا، برای ۵ روز', $item->directions('fa'));

        // amoxicillin to a patient allergic to penicillin, and paracetamol above the clinic's daily limit
        $para->update(['max_daily_mg' => 2000]);
        $amox = $this->med('amoxicillin-500-mg-capsule');
        $this->post('/prescriptions/'.$rx->id.'/items', ['medicine_id' => $amox->id, 'name' => 'x', 'dose_amount' => '1', 'dose_unit' => 'capsule', 'frequency' => 'tds', 'days' => '7']);
        $kinds = collect(Prescribing::warnings($rx->fresh()))->pluck('kind')->all();
        $this->assertContains('allergy', $kinds);
        $this->assertContains('dose', $kinds);
        $this->get('/prescriptions/'.$rx->id)->assertOk()->assertSee('the patient is allergic to Penicillin')->assertSee('Sign the prescription');

        // the warnings need their reasons, and the password must be right
        $this->from('/prescriptions/'.$rx->id)->post('/prescriptions/'.$rx->id.'/sign', ['password' => 'doctor-pass-1'])->assertSessionHas('danger');
        $reasons = collect(Prescribing::warnings($rx->fresh()))->mapWithKeys(fn ($w) => [$w['key'] => 'Discussed with the patient'])->all();
        $this->from('/prescriptions/'.$rx->id)->post('/prescriptions/'.$rx->id.'/sign', ['password' => 'wrong', 'reasons' => $reasons])->assertSessionHas('danger');
        $this->assertTrue($rx->fresh()->isDraft());
        $this->post('/prescriptions/'.$rx->id.'/sign', ['password' => 'doctor-pass-1', 'reasons' => $reasons])->assertSessionHas('success');
        $rx->refresh();
        $this->assertSame(['signed', 'RX-000001'], [$rx->status, $rx->number]);
        $this->assertTrue($rx->signatureHolds());
        $this->assertSame(count($reasons), $rx->warnings()->count());
        $this->assertSame(2, Medication::where('patient_id', $patient->id)->where('status', 'active')->where('note', 'RX-000001')->count());

        // locked
        $this->from('/prescriptions/'.$rx->id)->post('/prescriptions/'.$rx->id.'/items', ['name' => 'Something'])->assertSessionHas('danger');
        $this->assertSame(2, $rx->items()->count());

        // the paper, in Dari, with the licence and the QR code; the pharmacist's check without signing in
        $this->get('/prescriptions/'.$rx->id.'/print?lang=fa')->assertOk()->assertSee('RX-000001')->assertSee('AMC-1234')->assertSee('<svg', false)->assertSee('روزانه سه بار');
        auth()->logout();
        $this->get('/rx/'.$rx->verify_token)->assertOk()->assertSee('This prescription is genuine')->assertSee('G. A.')->assertDontSee('Gul Ahmad');
        $this->get('/rx/'.str_repeat('a', 32))->assertNotFound();

        // cancelled: the check page says so and the medicines are stopped
        $this->actingAs(User::where('email', 'ahmad@acme.test')->sole());
        $this->post('/prescriptions/'.$rx->id.'/cancel', ['reason' => 'Wrong patient'])->assertSessionHas('success');
        $this->assertSame(0, Medication::where('patient_id', $patient->id)->where('status', 'active')->count());
        $this->get('/rx/'.$rx->verify_token)->assertOk()->assertSee('Do not dispense');
        $this->get('/patients/'.$patient->id.'?tab=prescriptions')->assertOk()->assertSee('RX-000001');
    }

    public function test_a_childs_dose_is_checked_by_weight_and_a_set_is_saved_and_reused(): void
    {
        [$doctor, $patient] = $this->setUpDoctor(['name' => 'Bibi Zarghuna', 'gender' => 'female', 'age' => '4', 'allergies' => '']);
        $susp = $this->med('paracetamol-120-mg-5-ml-syrup');
        $susp->update(['child_max_mg_per_kg_dose' => 15]);
        $this->post('/prescriptions', ['patient_id' => $patient->id]);
        $rx = Prescription::sole();
        $this->post('/prescriptions/'.$rx->id.'/items', ['medicine_id' => $susp->id, 'name' => 'x', 'dose_amount' => '10', 'dose_unit' => 'ml', 'frequency' => 'q6h', 'days' => '3']);
        $this->assertSame('120 ml', $rx->items()->sole()->quantity);
        $this->assertSame(['dose'], collect(Prescribing::warnings($rx->fresh()))->pluck('kind')->all());   // no weight
        $this->put('/prescriptions/'.$rx->id, ['weight_kg' => '12']);
        $this->assertStringContainsString('15 mg/kg', Prescribing::warnings($rx->fresh())[0]['message']);   // 240 mg for 12 kg is 20 mg/kg
        $this->put('/prescriptions/'.$rx->id, ['weight_kg' => '20']);
        $this->assertSame([], Prescribing::warnings($rx->fresh()));   // 240 mg for 20 kg = 12 mg/kg

        $this->post('/prescriptions/'.$rx->id.'/save-set', ['name' => 'Fever, child'])->assertSessionHas('success');
        $this->post('/prescriptions/'.$rx->id.'/sign', ['password' => 'doctor-pass-1'])->assertSessionHas('success');
        $this->post('/prescriptions', ['patient_id' => $patient->id]);
        $second = Prescription::latest('id')->first();
        $this->assertNotSame($rx->id, $second->id);
        $this->post('/prescriptions/'.$second->id.'/set', ['set_id' => \App\Models\PrescriptionSet::sole()->id])->assertSessionHas('success');
        $this->assertSame(1, $second->items()->count());
    }

    public function test_only_the_doctor_of_the_prescription_writes_it(): void
    {
        [$doctor, $patient] = $this->setUpDoctor();
        $this->post('/prescriptions', ['patient_id' => $patient->id]);
        $rx = Prescription::sole();
        // the receptionist sees nothing; the owner (not a doctor) cannot start one
        $this->actingAs($this->userWith(['patients.view']));
        $this->get('/prescriptions/'.$rx->id)->assertForbidden();
        $this->actingAs(User::where('email', 'owner@acme.test')->sole());
        $this->from('/patients/'.$patient->id)->post('/prescriptions', ['patient_id' => $patient->id])->assertSessionHas('danger');
        $this->post('/prescriptions/'.$rx->id.'/items', ['name' => 'Paracetamol'])->assertForbidden();
    }

    public function test_the_qr_code_is_drawn(): void
    {
        $matrix = (new Qr)->make('https://acme.tabib.test/rx/'.str_repeat('a', 32));
        $this->assertCount(41, $matrix);   // version 6 for 56 bytes at level M: 41 x 41
        $this->assertTrue($matrix[0][0] && $matrix[6][6] && ! $matrix[7][7]);
        $this->assertStringStartsWith('<svg', Qr::svg('hello'));
    }
}
