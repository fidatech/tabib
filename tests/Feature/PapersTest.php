<?php

namespace Tests\Feature;

use App\Models\Encounter;
use App\Models\Letter;
use App\Models\Order;
use App\Models\Patient;
use App\Models\PatientDocument;
use App\Models\Prescription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/** The papers of the doctor: the history marked reviewed, requests for tests and their results, letters and certificates, the summary, a prescription written again and its card. */
class PapersTest extends TestCase
{
    use RefreshDatabase;

    private function visit(): array
    {
        $this->clinic();
        $this->owner();
        $this->post('/doctors', ['name' => 'Dr. Ahmad', 'email' => 'ahmad@acme.test', 'password' => 'doctor-pass-1', 'fee' => '300', 'colour' => 'teal', 'is_active' => '1', 'licence_no' => 'AMC-1234']);
        $this->post('/patients', ['name' => 'Gul Ahmad', 'father_name' => 'Karim', 'gender' => 'male', 'age' => '40', 'allergies' => 'Penicillin']);
        $this->actingAs(User::where('email', 'ahmad@acme.test')->sole());
        $this->post('/visits', ['patient_id' => Patient::sole()->id]);
        $visit = Encounter::sole();
        $this->put('/visits/'.$visit->id, ['complaint' => 'Fever and cough', 'examination' => 'Crackles right base', 'plan' => 'Azithromycin']);
        $this->post('/visits/'.$visit->id.'/diagnoses', ['description' => 'Pneumonia, unspecified organism', 'icd10' => 'J18.9']);

        return [$visit, Patient::sole()];
    }

    public function test_the_history_marked_reviewed_is_signed_with_the_note(): void
    {
        [$visit] = $this->visit();
        $this->post('/visits/'.$visit->id.'/reviewed', ['reviewed' => '1']);
        $this->assertNotNull($visit->fresh()->history_reviewed_at);
        $this->post('/visits/'.$visit->id.'/sign', ['password' => 'doctor-pass-1'])->assertSessionHas('success');
        $this->get('/visits/'.$visit->id)->assertSee('History reviewed by Dr. Ahmad');
        $this->assertTrue($visit->fresh()->signatureHolds());
        Encounter::whereKey($visit->id)->update(['history_reviewed_at' => null]);
        $this->assertFalse($visit->fresh()->signatureHolds());
    }

    public function test_a_lab_request_is_printed_and_its_result_comes_back_into_the_chart(): void
    {
        [$visit, $patient] = $this->visit();
        $this->get('/orders/create?patient='.$patient->id.'&visit='.$visit->id.'&kind=lab')->assertOk()->assertSee('CBC (complete blood count)')->assertSee('Pneumonia');
        $this->from('/orders/create')->post('/orders', ['patient_id' => $patient->id, 'encounter_id' => $visit->id, 'kind' => 'lab', 'urgency' => 'routine'])->assertSessionHas('danger');
        $this->post('/orders', ['patient_id' => $patient->id, 'encounter_id' => $visit->id, 'kind' => 'lab', 'tests' => ['CBC (complete blood count)', 'CRP'], 'other' => 'Sputum culture', 'urgency' => 'urgent'])
            ->assertSessionHas('success');
        $order = Order::sole();
        $this->assertSame(['LAB-000001', ['CBC (complete blood count)', 'CRP', 'Sputum culture'], $visit->id], [$order->number, $order->tests, $order->encounter_id]);
        $this->get('/orders/'.$order->id.'/print')->assertOk()->assertSee('Laboratory request')->assertSee('URGENT')->assertSee('Sputum culture')->assertSee('AMC-1234');

        // a nurse brings the result: written and the photo of the paper
        $this->post('/orders/'.$order->id.'/result', ['result' => 'WBC 14.2, CRP 48', 'file' => UploadedFile::fake()->create('cbc.pdf', 60, 'application/pdf')])->assertSessionHas('success');
        $order->refresh();
        $this->assertSame('resulted', $order->status);
        $this->assertSame('lab', PatientDocument::sole()->kind);
        $this->get('/patients/'.$patient->id.'?tab=papers')->assertOk()->assertSee('LAB-000001')->assertSee('Result in');
        $this->get('/patients/'.$patient->id.'?tab=timeline')->assertSee('WBC 14.2');

        $this->post('/orders', ['patient_id' => $patient->id, 'kind' => 'imaging', 'tests' => ['Chest X-ray (PA)'], 'urgency' => 'routine']);
        $xray = Order::where('kind', 'imaging')->sole();
        $this->assertSame('IMG-000001', $xray->number);
        $this->post('/orders/'.$xray->id.'/cancel', ['reason' => 'Done elsewhere'])->assertSessionHas('success');
        $this->from('/orders/'.$xray->id)->post('/orders/'.$xray->id.'/result', ['result' => 'x'])->assertSessionHas('danger');
    }

    public function test_letters_and_certificates_are_written_from_the_visit_given_once_and_cancelled_with_a_reason(): void
    {
        [$visit, $patient] = $this->visit();
        $this->get('/letters/create?patient='.$patient->id.'&visit='.$visit->id.'&kind=referral')->assertOk()->assertSee('Crackles right base')->assertSee('J18.9')->assertSee('Penicillin');
        $this->post('/letters', ['patient_id' => $patient->id, 'encounter_id' => $visit->id, 'kind' => 'referral', 'lang' => 'en', 'addressed_to' => 'Chest clinic, Wazir Akbar Khan hospital',
            'body' => 'Dear colleague, please see Gul Ahmad.'])->assertRedirect();
        $ref = Letter::sole();
        $this->assertSame('REF-000001', $ref->number);
        $this->assertTrue($ref->signatureHolds());
        $this->get('/letters/'.$ref->id)->assertOk()->assertSee('Referral letter')->assertSee('Wazir Akbar Khan');

        // three days of rest in Dari
        $page = $this->get('/letters/create?patient='.$patient->id.'&visit='.$visit->id.'&kind=sick_leave&lang=fa&from=2026-11-13&to=2026-11-15')->assertOk();
        $page->assertSee('برای 3 روز', false);
        $this->post('/letters', ['patient_id' => $patient->id, 'kind' => 'sick_leave', 'lang' => 'fa', 'rest_from' => '2026-11-13', 'rest_to' => '2026-11-15', 'body' => 'استراحت سه روز']);
        $mc = Letter::where('kind', 'sick_leave')->sole();
        $this->assertSame(['MC-000001', 3], [$mc->number, $mc->restDays()]);
        $this->get('/letters/'.$mc->id)->assertOk()->assertSee('تصدیق صحی', false);

        $this->post('/letters/'.$mc->id.'/cancel', ['reason' => 'Wrong dates'])->assertSessionHas('success');
        $this->assertSame('cancelled', $mc->fresh()->status);
        Letter::whereKey($ref->id)->update(['body' => 'Changed']);
        $this->get('/letters/'.$ref->id)->assertSee('Changed after it was given');
    }

    public function test_the_summary_and_a_prescription_written_again_with_its_card(): void
    {
        [$visit, $patient] = $this->visit();
        $this->post('/visits/'.$visit->id.'/sign', ['password' => 'doctor-pass-1']);
        $this->get('/patients/'.$patient->id.'/summary')->assertOk()->assertSee('Patient summary')->assertSee('Penicillin')->assertSee('Pneumonia');

        $this->post('/prescriptions', ['patient_id' => $patient->id, 'encounter_id' => $visit->id]);
        $rx = Prescription::sole();
        $azi = \App\Models\Medicine::where('name', 'Azithromycin')->where('form', 'tablet')->first();
        $this->post('/prescriptions/'.$rx->id.'/items', ['medicine_id' => $azi->id, 'name' => 'x', 'dose_amount' => '1', 'dose_unit' => 'tablet', 'frequency' => 'od', 'days' => '3']);
        $this->post('/prescriptions/'.$rx->id.'/sign', ['password' => 'doctor-pass-1'])->assertSessionHas('success');
        $card = $this->get('/prescriptions/'.$rx->id.'/card?lang=ps')->assertOk()->assertSee('سهار')->getContent();
        $this->assertSame(3, substr_count($card, 'class="tick"'));

        $this->post('/prescriptions/'.$rx->id.'/renew')->assertRedirect();
        $again = Prescription::latest('id')->first();
        $this->assertSame(['draft', 1, 'Azithromycin'], [$again->status, $again->items()->count(), $again->items()->first()->name]);
    }
}
