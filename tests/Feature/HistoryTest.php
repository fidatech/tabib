<?php

namespace Tests\Feature;

use App\Models\Central\Icd10Code;
use App\Models\History\Allergy;
use App\Models\History\Medication;
use App\Models\History\Problem;
use App\Models\HistoryRevision;
use App\Models\Patient;
use App\Models\PatientDocument;
use App\Models\PatientSocial;
use App\Models\Vital;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HistoryTest extends TestCase
{
    use RefreshDatabase;

    private function patient(array $over = []): Patient
    {
        $this->post('/patients', $over + ['name' => 'Gul Ahmad', 'gender' => 'male', 'age' => '50']);

        return Patient::latest('id')->firstOrFail();
    }

    public function test_the_history_is_written_changed_and_never_deleted(): void
    {
        $this->clinic();
        $this->owner();
        $patient = $this->patient(['allergies' => 'Penicillin, Aspirin']);
        $url = '/patients/'.$patient->id;

        // the allergies told at registration are entries, told by the patient
        $this->assertSame(['Penicillin', 'Aspirin'], Allergy::orderBy('id')->pluck('substance')->all());
        $this->assertSame(['patient'], Allergy::distinct()->pluck('source')->all());
        $this->get($url)->assertOk()->assertSee('Summary')->assertSee('patient-allergy', false)->assertSee('Penicillin');

        // a problem with its ICD-10 code, a medicine, a vaccine
        $this->post($url.'/history/problems', ['description' => 'Essential (primary) hypertension', 'icd10' => 'i10', 'onset_on' => '2020-01-01'])->assertRedirect();
        $this->post($url.'/history/medications', ['name' => 'Amlodipine', 'dose' => '5 mg', 'frequency' => 'once a day', 'route' => 'oral'])->assertRedirect();
        $this->post($url.'/history/immunizations', ['vaccine' => 'Tetanus', 'given_on' => '2025-05-01'])->assertRedirect();
        $this->post($url.'/history/problems', ['description' => ''])->assertSessionHasErrors('description');
        $this->post($url.'/history/allergies', ['substance' => 'Nuts', 'severity' => 'deadly'])->assertSessionHasErrors('severity');
        $problem = Problem::sole();
        $this->assertSame(['I10', 'active', 'clinician'], [$problem->icd10, $problem->status, $problem->source]);

        // changed: the old value is kept with the reason
        $this->put($url.'/history/medications/'.Medication::sole()->id, ['name' => 'Amlodipine', 'dose' => '10 mg', 'frequency' => 'once a day', 'reason' => 'BP still high'])->assertRedirect();
        $revision = HistoryRevision::where('subject_type', 'Medication')->where('action', 'updated')->sole();
        $this->assertSame(['from' => '5 mg', 'to' => '10 mg'], $revision->changes['dose']);
        $this->assertSame('BP still high', $revision->reason);
        // stopped
        $this->put($url.'/history/medications/'.Medication::sole()->id, ['name' => 'Amlodipine', 'dose' => '10 mg', 'status' => 'stopped', 'stopped_on' => '2026-11-13', 'stop_reason' => 'Swelling of the ankles'])->assertRedirect();
        $this->assertSame('stopped', Medication::sole()->status);

        // a mistake is marked with a reason, never deleted, and no longer counts
        $aspirin = Allergy::where('substance', 'Aspirin')->sole();
        $this->post($url.'/history/allergies/'.$aspirin->id.'/error', [])->assertSessionHasErrors('reason');
        $this->post($url.'/history/allergies/'.$aspirin->id.'/error', ['reason' => 'It was another patient'])->assertRedirect();
        $this->assertSame(['entered_in_error', 'It was another patient'], [$aspirin->fresh()->status, $aspirin->fresh()->error_reason]);
        $this->assertSame(1, HistoryRevision::where('subject_type', 'Allergy')->where('action', 'entered_in_error')->count());
        $this->from($url)->put($url.'/history/allergies/'.$aspirin->id, ['substance' => 'Aspirin'])->assertSessionHas('danger');
        $this->get($url)->assertOk()->assertSee('Penicillin');
        $this->get($url.'?tab=history')->assertOk()->assertSee('Essential (primary) hypertension')->assertSee('I10')->assertSee('It was another patient')
            ->assertSee('Tetanus')->assertSee('BP still high')->assertSee('Way of life');

        // the way of life: one row, its changes kept
        $this->post($url.'/social', ['smoking' => 'former', 'naswar' => 'current', 'occupation' => 'Driver'])->assertRedirect();
        $this->post($url.'/social', ['smoking' => 'never', 'naswar' => 'current', 'occupation' => 'Driver'])->assertRedirect();
        $this->assertSame(['never', 'current'], [PatientSocial::sole()->smoking, PatientSocial::sole()->naswar]);
        $this->assertSame(1, HistoryRevision::where('subject_type', 'PatientSocial')->where('action', 'updated')->count());

        // the summary reads only what is active; the timeline shows everything with what was a mistake struck through
        $this->get($url.'?tab=summary')->assertOk()->assertSee('Essential (primary) hypertension')->assertDontSee('Amlodipine');
        $this->get($url.'?tab=timeline')->assertOk()->assertSee('Tetanus')->assertSee('It was another patient')->assertSee('chart-timeline', false);
        $this->get($url.'?tab=timeline&only=vitals')->assertOk()->assertDontSee('Tetanus');
    }

    public function test_vital_signs_are_checked_and_drawn(): void
    {
        $this->clinic();
        $this->owner();
        $patient = $this->patient();
        $url = '/patients/'.$patient->id;

        $this->post($url.'/vitals', ['systolic' => '150', 'diastolic' => '95', 'pulse' => '88', 'temperature' => '37.2', 'weight' => '80', 'height' => '175'])->assertSessionHas('success');
        $this->post($url.'/vitals', ['systolic' => '140'])->assertSessionHas('danger');                // both numbers of the blood pressure
        $this->post($url.'/vitals', ['temperature' => '73'])->assertSessionHasErrors('temperature');   // not believable
        $this->post($url.'/vitals', [])->assertSessionHas('danger');                                  // nothing written
        $this->travel(2)->days();
        $this->post($url.'/vitals', ['systolic' => '135', 'diastolic' => '85', 'weight' => '78.5'])->assertSessionHas('success');

        $first = Vital::orderBy('id')->first();
        $this->assertSame([26.1, '150/95'], [$first->bmi(), $first->bloodPressure()]);
        $this->get($url.'?tab=vitals')->assertOk()->assertSee('vital-spark', false)->assertSee('150/95')->assertSee('135/85')->assertSee('26.1');
        $this->get($url.'?tab=summary')->assertOk()->assertSee('135/85');

        $this->post($url.'/history/vitals/'.$first->id.'/error', ['reason' => 'Wrong patient'])->assertRedirect();
        $this->assertSame('entered_in_error', $first->fresh()->status);
    }

    public function test_documents_are_kept_encrypted_and_opened_only_by_clinical_staff(): void
    {
        Storage::fake('local');
        $this->clinic();
        $this->owner();
        $patient = $this->patient();
        $url = '/patients/'.$patient->id;

        $this->post($url.'/documents', ['kind' => 'lab', 'title' => 'CBC', 'dated_on' => '2026-11-10', 'file' => UploadedFile::fake()->create('cbc.pdf', 80, 'application/pdf')])->assertSessionHas('success');
        $this->post($url.'/documents', ['kind' => 'lab', 'title' => 'Virus', 'file' => UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload')])->assertSessionHasErrors('file');
        $doc = PatientDocument::sole();
        $this->assertStringEndsWith('.enc', $doc->path);
        $this->get($url.'/documents/'.$doc->id)->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get($url.'?tab=documents')->assertOk()->assertSee('CBC');

        // another patient's address does not open it
        $other = $this->patient(['name' => 'Mariam']);
        $this->get('/patients/'.$other->id.'/documents/'.$doc->id)->assertNotFound();

        // the front desk sees the patient, not his clinical record
        $this->userWith(['patients.view', 'appointments.view']);
        $this->get($url)->assertOk()->assertSee('Coming appointments')->assertDontSee('?tab=history', false);
        $this->get($url.'?tab=history')->assertOk()->assertDontSee('Way of life');
        $this->get($url.'/documents/'.$doc->id)->assertForbidden();
        $this->post($url.'/history/problems', ['description' => 'X'])->assertForbidden();
        $this->getJson('/icd10?q=hyper')->assertForbidden();

        // a nurse writes the history and the vital signs
        $this->userWith(['patients.view', 'clinical.view', 'clinical.manage']);
        $this->post($url.'/vitals', ['pulse' => '80'])->assertSessionHas('success');
    }

    public function test_the_icd10_codes_are_loaded_and_found_by_code_or_words(): void
    {
        $this->artisan('icd10:import')->assertSuccessful();
        $this->assertGreaterThan(20000, Icd10Code::count());
        $this->assertSame('Essential (primary) hypertension', Icd10Code::find('I10')->description);

        $this->clinic();
        $this->owner();
        $this->assertSame('I10', $this->getJson('/icd10?q=i10')->assertOk()->json('results.0.code'));
        $found = collect($this->getJson('/icd10?q=type 2 diabetes')->json('results'))->pluck('code');
        $this->assertContains('E11.9', $found->all());
        $this->getJson('/icd10?q=x')->assertOk()->assertExactJson(['results' => []]);
    }
}
