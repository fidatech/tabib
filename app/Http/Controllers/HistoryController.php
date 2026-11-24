<?php

namespace App\Http\Controllers;

use App\Exceptions\UserError;
use App\Models\Central\Icd10Code;
use App\Models\History\Entry;
use App\Models\Patient;
use App\Models\PatientDocument;
use App\Models\PatientSocial;
use App\Models\Vital;
use App\Services\Vault;
use App\Support\History;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * Writing the history of a patient: the entries of every section (problems, allergies,
 * medicines ...), the way of life, vital signs and documents. Nothing is deleted: a mistake is
 * marked "entered in error" with a reason, and every change is kept (HasRevisions).
 */
class HistoryController extends Controller
{
    public function store(Request $request, int $patient, string $section): RedirectResponse
    {
        Gate::authorize('clinical.manage');
        $patient = Patient::findOrFail($patient);
        $def = History::section($section);
        $data = $this->validated($request, $def);

        /** @var Entry $entry */
        $entry = new $def['model'];
        $entry->fill($data + ['patient_id' => $patient->id, 'status' => $data['status'] ?? array_key_first($def['statuses']),
            'source' => $data['source'] ?? 'clinician', 'encounter_id' => $request->integer('encounter_id') ?: null,
            'created_by' => $request->user()->id, 'updated_by' => $request->user()->id])->save();

        return $this->back($request, $patient, $section)->with('success', __(':name added.', ['name' => __($def['singular'])]));
    }

    public function update(Request $request, int $patient, string $section, int $id): RedirectResponse
    {
        Gate::authorize('clinical.manage');
        $def = History::section($section);
        $entry = $def['model']::where('patient_id', $patient)->findOrFail($id);
        if ($entry->isError()) {
            throw new UserError(__('An entry marked as entered in error cannot be changed. Add a new one.'));
        }
        $data = $this->validated($request, $def);
        $entry->revisionReason = $request->input('reason') ? mb_substr((string) $request->input('reason'), 0, 255) : null;
        $entry->fill($data + ['updated_by' => $request->user()->id])->save();

        return $this->back($request, $entry->patient, $section)->with('success', __(':name saved.', ['name' => __($def['singular'])]));
    }

    /** A mistake: the entry stays, struck through, with the reason; it no longer counts anywhere. */
    public function error(Request $request, int $patient, string $section, int $id): RedirectResponse
    {
        Gate::authorize('clinical.manage');
        $reason = $request->validate(['reason' => ['required', 'string', 'max:255']])['reason'];
        $model = match ($section) {
            'vitals' => Vital::class,
            'documents' => PatientDocument::class,
            default => History::section($section)['model'],
        };
        $entry = $model::where('patient_id', $patient)->findOrFail($id);
        $entry->revisionReason = $reason;
        $entry->forceFill(['status' => History::ERROR, 'error_reason' => $reason])->save();

        return $this->back($request, Patient::findOrFail($patient), $section)->with('success', __('Marked as entered in error.'));
    }

    public function social(Request $request, int $patient): RedirectResponse
    {
        Gate::authorize('clinical.manage');
        $patient = Patient::findOrFail($patient);
        $habit = ['nullable', Rule::in(array_keys(PatientSocial::HABITS))];
        $data = $request->validate([
            'smoking' => $habit, 'naswar' => $habit, 'alcohol' => $habit,
            'occupation' => ['nullable', 'string', 'max:150'],
            'marital_status' => ['nullable', Rule::in(array_keys(PatientSocial::MARITAL))],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        $social = PatientSocial::firstOrNew(['patient_id' => $patient->id]);
        $social->fill($data + ['updated_by' => $request->user()->id])->save();

        return $this->back($request, $patient, 'social')->with('success', __('Saved.'));
    }

    public function vitals(Request $request, int $patient): RedirectResponse
    {
        Gate::authorize('clinical.manage');
        $patient = Patient::findOrFail($patient);
        $rules = [];
        foreach (Vital::MEASURES as $name => [$label, $unit, $low, $high]) {
            $rules[$name] = ['nullable', 'numeric', 'between:'.$low.','.$high];
        }
        $data = $request->validate($rules + ['encounter_id' => ['nullable', 'integer']]);
        $values = array_filter(array_intersect_key($data, Vital::MEASURES), fn ($v) => $v !== null && $v !== '');
        if (! $values) {
            throw new UserError(__('Write at least one measure.'));
        }
        if (isset($values['systolic']) !== isset($values['diastolic'])) {
            throw new UserError(__('Write both numbers of the blood pressure.'));
        }
        Vital::create($values + ['patient_id' => $patient->id, 'encounter_id' => $data['encounter_id'] ?? null, 'taken_at' => now(), 'created_by' => $request->user()->id]);

        return $this->back($request, $patient, 'vitals')->with('success', __('Vital signs saved.'));
    }

    public function upload(Request $request, int $patient): RedirectResponse
    {
        Gate::authorize('clinical.manage');
        $patient = Patient::findOrFail($patient);
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
            'kind' => ['required', Rule::in(array_keys(PatientDocument::KINDS))],
            'title' => ['required', 'string', 'max:150'],
            'dated_on' => ['nullable', 'date', 'before_or_equal:today'],
        ]);
        $file = $data['file'];
        DB::transaction(fn () => PatientDocument::create([
            'patient_id' => $patient->id, 'kind' => $data['kind'], 'title' => $data['title'], 'dated_on' => $data['dated_on'] ?? null,
            'path' => Vault::put($file, 'patients/'.$patient->id), 'original_name' => mb_substr($file->getClientOriginalName(), 0, 200),
            'mime' => (string) $file->getMimeType(), 'size' => (int) $file->getSize(), 'encounter_id' => $request->integer('encounter_id') ?: null,
            'created_by' => $request->user()->id,
        ]));

        return $this->back($request, $patient, 'documents')->with('success', __('Document added.'));
    }

    /** A document, opened in the browser by someone who may read the clinical record; every opening is written down. */
    public function document(int $patient, int $id): Response
    {
        Gate::authorize('clinical.view');
        $doc = PatientDocument::where('patient_id', $patient)->kept()->findOrFail($id);
        $contents = Vault::get($doc->path);
        abort_if($contents === null, 404);
        audit('viewed', $doc, 'Document '.$doc->title.' of patient '.$patient);

        return response($contents, 200, [
            'Content-Type' => $doc->mime,
            'Content-Disposition' => 'inline; filename="'.addcslashes($doc->original_name, '"\\').'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** What the diagnosis box asks as letters are typed. */
    public function icd10(Request $request): JsonResponse
    {
        Gate::authorize('clinical.view');

        return response()->json(['results' => Icd10Code::lookup((string) $request->query('q', ''))->map(fn (Icd10Code $c) => [
            'code' => $c->code, 'text' => $c->description_local && text_dir() === 'rtl' ? $c->description_local : $c->description, 'billable' => $c->billable,
        ])->values()]);
    }

    private function validated(Request $request, array $def): array
    {
        $labels = [];
        foreach ($def['fields'] as $name => $field) {
            $labels[$name] = mb_strtolower(__($field[0]));
        }
        $data = $request->validate(History::rules($def), [], $labels);
        if (array_key_exists('icd10', $data) && $data['icd10']) {
            $data['icd10'] = strtoupper(trim($data['icd10']));
        }

        return $data;
    }

    /** Back to where the form was: the visit being written, or the tab of the patient's page. */
    private function back(Request $request, Patient $patient, string $section): RedirectResponse
    {
        if ($encounter = $request->integer('encounter_id')) {
            return redirect()->route('encounters.show', ['id' => $encounter]);
        }
        $tab = in_array($section, ['vitals', 'documents'], true) ? $section : 'history';

        return redirect()->to(route('patients.show', ['id' => $patient->id, 'tab' => $tab]).($tab === 'history' ? '#h-'.$section : ''));
    }
}
