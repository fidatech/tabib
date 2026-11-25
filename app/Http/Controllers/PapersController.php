<?php

namespace App\Http\Controllers;

use App\Exceptions\UserError;
use App\Models\Encounter;
use App\Models\Letter;
use App\Models\Order;
use App\Models\Patient;
use App\Models\PatientDocument;
use App\Models\Prescription;
use App\Services\Papers;
use App\Services\Prescribing;
use App\Services\Sequence;
use App\Services\Vault;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The papers of the doctor: lab and imaging requests (and their results), referral letters and
 * medical certificates, the summary of a patient, a prescription written again, and the card that
 * shows a patient when to take his medicines.
 */
class PapersController extends Controller
{
    /* ------------------------------------------------------------ lab and imaging */

    public function createOrder(Request $request): View
    {
        Gate::authorize('clinical.manage');
        $this->doctor($request);
        $patient = Patient::findOrFail($request->integer('patient'));
        $kind = $request->query('kind') === 'imaging' ? 'imaging' : 'lab';

        return view('papers.order-form', ['patient' => $patient, 'kind' => $kind, 'visit' => $this->visit($request, $patient)]);
    }

    public function storeOrder(Request $request): RedirectResponse
    {
        Gate::authorize('clinical.manage');
        $doctor = $this->doctor($request);
        $data = $request->validate([
            'patient_id' => ['required', 'integer'], 'encounter_id' => ['nullable', 'integer'], 'kind' => ['required', Rule::in(array_keys(Order::KINDS))],
            'tests' => ['nullable', 'array'], 'tests.*' => ['string', 'max:120'], 'other' => ['nullable', 'string', 'max:300'],
            'urgency' => ['required', Rule::in(['routine', 'urgent'])], 'clinical_note' => ['nullable', 'string', 'max:500'],
        ]);
        $patient = Patient::findOrFail($data['patient_id']);
        $tests = array_values(array_unique(array_filter(array_merge((array) ($data['tests'] ?? []), array_map('trim', explode(',', (string) ($data['other'] ?? '')))))));
        if (! $tests) {
            throw new UserError(__('Choose or write at least one test.'));
        }
        $order = Order::create([
            'number' => Sequence::next($data['kind'] === 'lab' ? 'LAB' : 'IMG'), 'patient_id' => $patient->id, 'encounter_id' => $this->visit($request, $patient, 'encounter_id')?->id,
            'doctor_id' => $doctor->id, 'branch_id' => current_branch()->id, 'kind' => $data['kind'], 'tests' => $tests, 'urgency' => $data['urgency'],
            'clinical_note' => $data['clinical_note'] ?? null, 'status' => 'ordered', 'created_by' => $request->user()->id,
        ]);

        return redirect()->route('orders.show', ['id' => $order->id])->with('success', __('Request :number made. Print it for the patient.', ['number' => $order->number]));
    }

    public function showOrder(int $id): View
    {
        Gate::authorize('clinical.view');
        $order = Order::with(['patient', 'doctor.user', 'encounter', 'document', 'resulter'])->findOrFail($id);

        return view('papers.order', ['order' => $order, 'patient' => $order->patient]);
    }

    public function printOrder(int $id): View
    {
        Gate::authorize('clinical.view');
        $order = Order::with(['patient', 'doctor.user'])->findOrFail($id);
        audit('printed', $order);

        return view('papers.paper', ['what' => 'order', 'order' => $order, 'patient' => $order->patient, 'doctor' => $order->doctor, 'title' => __(Order::KINDS[$order->kind]).' · '.$order->number]);
    }

    /** The result comes back: written, uploaded (it goes in the documents of the patient), or both. */
    public function result(Request $request, int $id): RedirectResponse
    {
        Gate::authorize('clinical.manage');
        $order = Order::findOrFail($id);
        if ($order->status === 'cancelled') {
            throw new UserError(__('This request is cancelled.'));
        }
        $data = $request->validate(['result' => ['nullable', 'string', 'max:5000'], 'file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240']]);
        if (blank($data['result'] ?? null) && ! $request->hasFile('file')) {
            throw new UserError(__('Write the result or add the file of it.'));
        }
        DB::transaction(function () use ($request, $order, $data) {
            $doc = null;
            if ($file = $request->file('file')) {
                $doc = PatientDocument::create([
                    'patient_id' => $order->patient_id, 'kind' => $order->kind === 'lab' ? 'lab' : 'imaging', 'title' => mb_substr($order->number.' · '.$order->summary(), 0, 150),
                    'dated_on' => today_iso(), 'path' => Vault::put($file, 'patients/'.$order->patient_id), 'original_name' => mb_substr($file->getClientOriginalName(), 0, 200),
                    'mime' => (string) $file->getMimeType(), 'size' => (int) $file->getSize(), 'encounter_id' => $order->encounter_id, 'created_by' => $request->user()->id,
                ]);
            }
            $order->update(['status' => 'resulted', 'result' => $data['result'] ?? $order->result, 'result_document_id' => $doc?->id ?? $order->result_document_id,
                'resulted_at' => now(), 'resulted_by' => $request->user()->id]);
        });

        return redirect()->route('orders.show', ['id' => $order->id])->with('success', __('The result is in the chart.'));
    }

    public function cancelOrder(Request $request, int $id): RedirectResponse
    {
        Gate::authorize('clinical.manage');
        $order = Order::findOrFail($id);
        abort_unless($order->status === 'ordered', 403);
        $order->update(['status' => 'cancelled', 'cancel_reason' => $request->validate(['reason' => ['required', 'string', 'max:255']])['reason']]);

        return redirect()->route('orders.show', ['id' => $order->id])->with('success', __('The request is cancelled.'));
    }

    /* ------------------------------------------------------------ letters and certificates */

    public function createLetter(Request $request): View
    {
        Gate::authorize('clinical.manage');
        $this->doctor($request);
        $patient = Patient::findOrFail($request->integer('patient'));
        $kind = array_key_exists((string) $request->query('kind'), Letter::KINDS) ? (string) $request->query('kind') : 'referral';
        $lang = in_array($request->query('lang'), ['en', 'fa', 'ps'], true) ? $request->query('lang') : (in_array(app()->getLocale(), ['fa', 'ps'], true) ? app()->getLocale() : 'en');
        $visit = $this->visit($request, $patient);
        $from = $request->query('from') ?: today_iso();
        $to = $request->query('to') ?: ($kind === 'sick_leave' ? \Illuminate\Support\Carbon::parse(today_iso())->addDays(2)->toDateString() : null);

        return view('papers.letter-form', ['patient' => $patient, 'kind' => $kind, 'lang' => $lang, 'visit' => $visit, 'from' => $from, 'to' => $to,
            'body' => Papers::draft($kind, $lang, $patient, $visit, $kind === 'sick_leave' ? $from : null, $kind === 'sick_leave' ? $to : null)]);
    }

    public function storeLetter(Request $request): RedirectResponse
    {
        Gate::authorize('clinical.manage');
        $doctor = $this->doctor($request);
        $data = $request->validate([
            'patient_id' => ['required', 'integer'], 'encounter_id' => ['nullable', 'integer'], 'kind' => ['required', Rule::in(array_keys(Letter::KINDS))],
            'lang' => ['required', Rule::in(['en', 'fa', 'ps'])], 'addressed_to' => ['nullable', 'string', 'max:200'],
            'rest_from' => ['nullable', 'required_if:kind,sick_leave', 'date'], 'rest_to' => ['nullable', 'required_if:kind,sick_leave', 'date', 'after_or_equal:rest_from'],
            'body' => ['required', 'string', 'max:5000'],
        ]);
        $patient = Patient::findOrFail($data['patient_id']);
        if ($data['kind'] !== 'sick_leave') {
            $data['rest_from'] = $data['rest_to'] = null;
        }
        $letter = Papers::issue(['patient_id' => $patient->id, 'encounter_id' => $this->visit($request, $patient, 'encounter_id')?->id] + array_intersect_key($data, array_flip(['kind', 'lang', 'addressed_to', 'rest_from', 'rest_to', 'body'])),
            $doctor->id, $request->user()->id);

        return redirect()->route('letters.show', ['id' => $letter->id]);
    }

    public function showLetter(int $id): View
    {
        Gate::authorize('clinical.view');
        $letter = Letter::with(['patient', 'doctor.user'])->findOrFail($id);
        audit('printed', $letter);

        return view('papers.paper', ['what' => 'letter', 'letter' => $letter, 'patient' => $letter->patient, 'doctor' => $letter->doctor, 'title' => $letter->kindLabel().' · '.$letter->number]);
    }

    public function cancelLetter(Request $request, int $id): RedirectResponse
    {
        Gate::authorize('clinical.manage');
        $letter = Letter::findOrFail($id);
        abort_unless($letter->status === 'issued' && Prescribing::doctorOf($request->user()->id)?->id === $letter->doctor_id, 403);
        $letter->update(['status' => 'cancelled', 'cancel_reason' => $request->validate(['reason' => ['required', 'string', 'max:255']])['reason']]);

        return redirect()->route('patients.show', ['id' => $letter->patient_id, 'tab' => 'papers'])->with('success', __(':number is cancelled.', ['number' => $letter->number]));
    }

    /* ------------------------------------------------------------ the summary of a patient */

    public function summary(int $id): View
    {
        Gate::authorize('clinical.view');
        $patient = Patient::findOrFail($id);
        audit('printed', $patient, 'Summary of '.$patient->number);
        $kept = fn (string $model) => $model::where('patient_id', $patient->id)->kept()->get();

        return view('papers.summary', [
            'patient' => $patient,
            'problems' => $kept(\App\Models\History\Problem::class),
            'allergies' => $kept(\App\Models\History\Allergy::class),
            'medications' => \App\Models\History\Medication::where('patient_id', $patient->id)->where('status', 'active')->get(),
            'surgeries' => $kept(\App\Models\History\Surgery::class),
            'family' => $kept(\App\Models\History\FamilyHistory::class),
            'immunizations' => $kept(\App\Models\History\Immunization::class),
            'vitals' => \App\Models\Vital::where('patient_id', $patient->id)->kept()->latest('taken_at')->limit(5)->get(),
            'visits' => Encounter::with(['doctor.user', 'diagnoses'])->where('patient_id', $patient->id)->where('status', 'signed')->latest('started_at')->limit(15)->get(),
            'orders' => Order::where('patient_id', $patient->id)->where('status', 'resulted')->latest('resulted_at')->limit(10)->get(),
        ]);
    }

    /* ------------------------------------------------------------ prescriptions */

    /** The same medicines again, as a new draft of the doctor who writes it now. */
    public function renew(Request $request, int $id): RedirectResponse
    {
        Gate::authorize('prescriptions.manage');
        $doctor = Prescribing::doctorOf($request->user()->id) ?? throw new UserError(__('Only a doctor of the clinic writes a prescription. Add yourself as a doctor first.'));
        $old = Prescription::with('items.medicine')->findOrFail($id);
        abort_if($old->status === Prescription::DRAFT, 404);
        $rx = DB::transaction(function () use ($old, $doctor, $request) {
            $rx = Prescription::create(['patient_id' => $old->patient_id, 'doctor_id' => $doctor->id, 'branch_id' => current_branch()->id,
                'weight_kg' => Prescribing::recentWeight($old->patient) ?? $old->weight_kg, 'notes' => $old->notes, 'status' => Prescription::DRAFT, 'created_by' => $request->user()->id]);
            foreach ($old->items as $item) {
                if ($item->medicine && ($item->medicine->withdrawn || ! $item->medicine->is_active)) {
                    continue;   // taken off the list since: the doctor chooses another
                }
                $rx->items()->create($item->only(['medicine_id', 'name', 'strength', 'form', 'route', 'dose_amount', 'dose_unit', 'frequency', 'timing', 'days', 'quantity', 'instructions', 'position']));
            }

            return $rx;
        });

        return redirect()->route('prescriptions.show', ['id' => $rx->id])->with('success', __('The medicines of :number are in a new prescription. Check them, then sign.', ['number' => $old->number]));
    }

    /** A card for the patient: every medicine, the parts of the day, and a box to tick for each day. */
    public function card(Request $request, int $id): View
    {
        Gate::authorize('prescriptions.view');
        $rx = Prescription::with(['patient', 'doctor.user', 'items'])->findOrFail($id);
        abort_unless($rx->isSigned(), 404);
        $lang = in_array($request->query('lang'), ['en', 'fa', 'ps'], true) ? $request->query('lang') : (in_array(app()->getLocale(), ['fa', 'ps'], true) ? app()->getLocale() : 'en');

        return view('papers.card', ['rx' => $rx, 'patient' => $rx->patient, 'lang' => $lang]);
    }

    /* ------------------------------------------------------------ */

    private function doctor(Request $request): \App\Models\Doctor
    {
        return Prescribing::doctorOf($request->user()->id) ?? throw new UserError(__('Only a doctor of the clinic writes this. Add yourself as a doctor first.'));
    }

    private function visit(Request $request, Patient $patient, string $key = 'visit'): ?Encounter
    {
        $id = $request->integer($key);

        return $id ? Encounter::where('patient_id', $patient->id)->find($id) : null;
    }
}
