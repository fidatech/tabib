<?php

namespace App\Http\Controllers;

use App\Exceptions\UserError;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Encounter;
use App\Models\EncounterAddendum;
use App\Models\History\Problem;
use App\Models\Patient;
use App\Models\Vital;
use App\Services\Scheduling;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * The visit with the doctor. The doctor opens it from the queue (or the patient's page), writes
 * the note while the patient is in, adds the diagnoses, and signs: from then on the visit is locked
 * and only addenda can be added. Only the doctor of the visit writes and signs it.
 */
class EncounterController extends Controller
{
    /** Open the visit of an appointment, or a new visit of a patient. A visit already open is opened again, not made twice. */
    public function start(Request $request): RedirectResponse
    {
        Gate::authorize('clinical.manage');
        $doctor = $this->doctor($request);
        $data = $request->validate(['appointment_id' => ['nullable', 'integer'], 'patient_id' => ['required_without:appointment_id', 'nullable', 'integer']]);

        $encounter = DB::transaction(function () use ($data, $doctor, $request) {
            $appointment = ! empty($data['appointment_id']) ? Appointment::lockForUpdate()->findOrFail($data['appointment_id']) : null;
            if ($appointment && ($existing = Encounter::where('appointment_id', $appointment->id)->first())) {
                return $existing;
            }
            if ($appointment && in_array($appointment->status, [Appointment::BOOKED, Appointment::CONFIRMED], true)) {
                Scheduling::act($appointment, 'arrive');
            }
            if ($appointment && $appointment->fresh()->status === Appointment::ARRIVED) {
                Scheduling::act($appointment, 'start');
            }
            $patient = Patient::findOrFail($appointment?->patient_id ?? $data['patient_id']);

            return Encounter::create([
                'patient_id' => $patient->id, 'appointment_id' => $appointment?->id, 'doctor_id' => $doctor->id, 'branch_id' => current_branch()->id,
                'started_at' => now(), 'complaint' => $appointment?->reason, 'status' => Encounter::DRAFT, 'created_by' => $request->user()->id,
            ]);
        });

        return redirect()->route('encounters.show', ['id' => $encounter->id]);
    }

    public function show(Request $request, int $id): View
    {
        Gate::authorize('clinical.view');
        $encounter = Encounter::with(['patient', 'doctor.user', 'diagnoses', 'addenda.author', 'vitals', 'signer', 'appointment', 'reviewer'])->findOrFail($id);
        audit('viewed', $encounter);
        $patient = $encounter->patient;

        return view('encounters.show', [
            'encounter' => $encounter,
            'patient' => $patient,
            'canWrite' => ! $encounter->isSigned() && $this->writes($request, $encounter),
            'canAdd' => $encounter->isSigned() && $request->user()->can('clinical.manage'),
            'allergies' => \App\Models\History\Allergy::where('patient_id', $patient->id)->where('status', 'active')->get(),
            'problems' => Problem::where('patient_id', $patient->id)->where('status', 'active')->orderBy('description')->get(),
            'medications' => \App\Models\History\Medication::where('patient_id', $patient->id)->where('status', 'active')->orderBy('name')->get(),
            'lastVital' => Vital::where('patient_id', $patient->id)->kept()->latest('taken_at')->first(),
            'templates' => \App\Models\NoteTemplate::where('is_active', true)->orderBy('name')->get(),
            'previous' => Encounter::with(['diagnoses', 'doctor.user'])->where('patient_id', $patient->id)->where('id', '!=', $encounter->id)
                ->where('status', Encounter::SIGNED)->latest('started_at')->limit(5)->get(),
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $encounter = $this->draft($request, $id);
        $data = $request->validate($this->noteRules() + \App\Support\Forms::rules($encounter->kind));
        $extra = $data['extra'] ?? [];
        unset($data['extra']);
        if ($encounter->kind) {
            $data['extra'] = \App\Support\Forms::clean($encounter->kind, (array) $extra);
        }
        $encounter->update($data);

        return redirect()->route('encounters.show', ['id' => $encounter->id])->with('success', __('The note is saved (draft).'));
    }

    /** The doctor says he read the history of the patient in this visit: who and when is kept, and signed with the note. */
    public function reviewed(Request $request, int $id): RedirectResponse
    {
        $encounter = $this->draft($request, $id);
        $on = $request->boolean('reviewed');
        $encounter->update(['history_reviewed_at' => $on ? now()->startOfSecond() : null, 'history_reviewed_by' => $on ? $request->user()->id : null]);

        return redirect()->route('encounters.show', ['id' => $encounter->id]);
    }

    /** A template fills the empty parts of the note and brings its form (tooth chart, pregnancy). */
    public function template(Request $request, int $id): RedirectResponse
    {
        $encounter = $this->draft($request, $id);
        $template = \App\Models\NoteTemplate::where('is_active', true)->findOrFail((int) $request->input('template_id'));
        $fill = ['template_id' => $template->id, 'kind' => $template->kind === 'general' ? $encounter->kind : $template->kind];
        foreach (array_keys(Encounter::PARTS) as $part) {
            if (blank($encounter->{$part}) && filled($template->{$part})) {
                $fill[$part] = $template->{$part};
            }
        }
        $encounter->update($fill);

        return redirect()->route('encounters.show', ['id' => $encounter->id])->with('success', __('The template :name is in the note.', ['name' => $template->name]));
    }

    public function addDiagnosis(Request $request, int $id): RedirectResponse
    {
        $encounter = $this->draft($request, $id);
        $data = $request->validate([
            'description' => ['required', 'string', 'max:255'],
            'icd10' => ['nullable', 'string', 'max:10'],
            'to_problems' => ['nullable', 'boolean'],
        ]);
        $code = $data['icd10'] ? strtoupper(trim($data['icd10'])) : null;
        DB::transaction(function () use ($encounter, $data, $code, $request) {
            $encounter->diagnoses()->create(['icd10' => $code, 'description' => $data['description'], 'is_primary' => ! $encounter->diagnoses()->exists()]);
            // a long-standing problem (diabetes, hypertension) also goes on the problem list of the patient
            if (! empty($data['to_problems']) && ! Problem::where('patient_id', $encounter->patient_id)->where('status', 'active')
                ->where(fn ($q) => $code ? $q->where('icd10', $code) : $q->where('description', $data['description']))->exists()) {
                Problem::create(['patient_id' => $encounter->patient_id, 'encounter_id' => $encounter->id, 'description' => $data['description'], 'icd10' => $code,
                    'onset_on' => today_iso(), 'status' => 'active', 'created_by' => $request->user()->id, 'updated_by' => $request->user()->id]);
            }
        });

        return redirect()->route('encounters.show', ['id' => $encounter->id])->with('success', __('Diagnosis added.'));
    }

    public function removeDiagnosis(Request $request, int $id, int $diagnosis): RedirectResponse
    {
        $encounter = $this->draft($request, $id);
        $encounter->diagnoses()->whereKey($diagnosis)->delete();
        // the first one left is the main diagnosis
        if (! $encounter->diagnoses()->where('is_primary', true)->exists()) {
            $encounter->diagnoses()->orderBy('id')->first()?->update(['is_primary' => true]);
        }

        return redirect()->route('encounters.show', ['id' => $encounter->id]);
    }

    /** The doctor signs with his password: the note is locked, and the appointment of the visit is done. */
    public function sign(Request $request, int $id): RedirectResponse
    {
        $encounter = $this->draft($request, $id);
        $note = $request->validate(['password' => ['required', 'string']] + $this->noteRules());
        unset($note['password']);
        if (! Hash::check((string) $request->input('password'), (string) $request->user()->password)) {
            throw new UserError(__('The password is not right. The visit is not signed.'));
        }
        // what was in the boxes when "Save and sign" was pressed is what is signed
        if ($note) {
            $encounter->update($note);
        }
        if (blank($encounter->complaint) && blank($encounter->assessment) && ! $encounter->diagnoses()->exists()) {
            throw new UserError(__('Write at least the complaint, the assessment or a diagnosis before signing.'));
        }

        DB::transaction(function () use ($encounter, $request) {
            $encounter->forceFill(['status' => Encounter::SIGNED, 'signed_at' => now()->startOfSecond(), 'signed_by' => $request->user()->id]);
            $encounter->signature = hash('sha256', $encounter->signedContent());
            $encounter->save();
            if ($encounter->appointment && in_array($encounter->appointment->status, [Appointment::ARRIVED, Appointment::WITH_DOCTOR], true)) {
                Scheduling::act($encounter->appointment, 'complete');
            }
        });

        return redirect()->route('encounters.show', ['id' => $encounter->id])->with('success', __('The visit is signed and locked.'));
    }

    /** Something added to a signed visit: a lab result that came later, a correction. */
    public function addendum(Request $request, int $id): RedirectResponse
    {
        Gate::authorize('clinical.manage');
        $encounter = Encounter::findOrFail($id);
        if (! $encounter->isSigned()) {
            throw new UserError(__('A visit that is not signed is changed directly.'));
        }
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);
        EncounterAddendum::create(['encounter_id' => $encounter->id, 'body' => $data['body'], 'created_by' => $request->user()->id]);
        audit('updated', $encounter, 'Addendum added');

        return redirect()->route('encounters.show', ['id' => $encounter->id])->with('success', __('Addendum added.'));
    }

    private function noteRules(): array
    {
        $rules = ['follow_up_on' => ['nullable', 'date', 'after_or_equal:today']];
        foreach (array_keys(Encounter::PARTS) as $part) {
            $rules[$part] = ['nullable', 'string', 'max:10000'];
        }

        return $rules;
    }

    /** A draft that the signed-in doctor may write: it is his visit and it is not signed. */
    private function draft(Request $request, int $id): Encounter
    {
        Gate::authorize('clinical.manage');
        $encounter = Encounter::findOrFail($id);
        if ($encounter->isSigned()) {
            throw new UserError(__('This visit is signed and locked. Add an addendum instead.'));
        }
        abort_unless($this->writes($request, $encounter), 403);

        return $encounter;
    }

    private function writes(Request $request, Encounter $encounter): bool
    {
        return $request->user()->can('clinical.manage') && Doctor::where('user_id', $request->user()->id)->value('id') === $encounter->doctor_id;
    }

    /** Only a doctor of the clinic opens a visit. */
    private function doctor(Request $request): Doctor
    {
        $doctor = Doctor::where('user_id', $request->user()->id)->where('is_active', true)->first();
        if (! $doctor) {
            throw new UserError(__('Only a doctor of the clinic opens a visit. Add yourself as a doctor first.'));
        }

        return $doctor;
    }
}
