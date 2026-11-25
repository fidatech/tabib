<?php

namespace App\Http\Controllers;

use App\Exceptions\UserError;
use App\Models\Encounter;
use App\Models\Medicine;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\PrescriptionSet;
use App\Services\Prescribing;
use App\Support\Qr;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Writing a prescription: the doctor of the patient starts it (from a visit or from the chart),
 * adds medicines one by one or from a saved set, sees the warnings, and signs with his password,
 * giving a reason for every warning. A signed prescription is printed with a QR code that a
 * pharmacist opens to see that it is real; it can be cancelled, never changed.
 */
class PrescriptionController extends Controller
{
    public function start(Request $request): RedirectResponse
    {
        Gate::authorize('prescriptions.manage');
        $doctor = Prescribing::doctorOf($request->user()->id) ?? throw new UserError(__('Only a doctor of the clinic writes a prescription. Add yourself as a doctor first.'));
        $data = $request->validate(['patient_id' => ['required', 'integer'], 'encounter_id' => ['nullable', 'integer']]);
        $patient = Patient::findOrFail($data['patient_id']);
        $encounter = ! empty($data['encounter_id']) ? Encounter::where('patient_id', $patient->id)->findOrFail($data['encounter_id']) : null;

        // a draft of the same doctor for the same visit (or patient, today) is opened again, not made twice
        $rx = Prescription::where('patient_id', $patient->id)->where('doctor_id', $doctor->id)->where('status', Prescription::DRAFT)
            ->when($encounter, fn ($q) => $q->where('encounter_id', $encounter->id), fn ($q) => $q->whereNull('encounter_id')->where('created_at', '>=', now()->startOfDay()))
            ->first()
            ?? Prescription::create(['patient_id' => $patient->id, 'encounter_id' => $encounter?->id, 'doctor_id' => $doctor->id, 'branch_id' => current_branch()->id,
                'weight_kg' => Prescribing::recentWeight($patient), 'status' => Prescription::DRAFT, 'created_by' => $request->user()->id]);

        return redirect()->route('prescriptions.show', ['id' => $rx->id]);
    }

    public function show(Request $request, int $id): View
    {
        Gate::authorize('prescriptions.view');
        $rx = Prescription::with(['patient', 'doctor.user', 'items.medicine', 'warnings', 'signer', 'encounter'])->findOrFail($id);
        $canWrite = $rx->isDraft() && $this->writes($request, $rx);

        return view('prescriptions.show', [
            'rx' => $rx,
            'patient' => $rx->patient,
            'canWrite' => $canWrite,
            'canCancel' => $rx->isSigned() && $this->writes($request, $rx),
            'warnings' => $rx->isDraft() ? Prescribing::warnings($rx) : [],
            'allergies' => \App\Models\History\Allergy::where('patient_id', $rx->patient_id)->where('status', 'active')->get(),
            'sets' => $canWrite ? PrescriptionSet::where('doctor_id', $rx->doctor_id)->orderBy('name')->get() : collect(),
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $rx = $this->draft($request, $id);
        $rx->update($request->validate(['weight_kg' => ['nullable', 'numeric', 'between:0.5,350'], 'notes' => ['nullable', 'string', 'max:1000']]));

        return redirect()->route('prescriptions.show', ['id' => $rx->id]);
    }

    public function addItem(Request $request, int $id): RedirectResponse
    {
        $rx = $this->draft($request, $id);
        $data = $this->itemData($request);
        $rx->items()->create($data + ['position' => (int) $rx->items()->max('position') + 1]);

        return redirect()->route('prescriptions.show', ['id' => $rx->id])->with('success', __(':name added.', ['name' => $data['name']]));
    }

    public function updateItem(Request $request, int $id, int $item): RedirectResponse
    {
        $rx = $this->draft($request, $id);
        $rx->items()->whereKey($item)->firstOrFail()->update($this->itemData($request));

        return redirect()->route('prescriptions.show', ['id' => $rx->id]);
    }

    public function removeItem(Request $request, int $id, int $item): RedirectResponse
    {
        $rx = $this->draft($request, $id);
        $rx->items()->whereKey($item)->delete();

        return redirect()->route('prescriptions.show', ['id' => $rx->id]);
    }

    /** The medicines of a saved set are added in one go. */
    public function applySet(Request $request, int $id): RedirectResponse
    {
        $rx = $this->draft($request, $id);
        $set = PrescriptionSet::where('doctor_id', $rx->doctor_id)->findOrFail((int) $request->input('set_id'));
        DB::transaction(function () use ($rx, $set) {
            $position = (int) $rx->items()->max('position');
            foreach ($set->items as $item) {
                $medicine = ! empty($item['medicine_id']) ? Medicine::find($item['medicine_id']) : null;
                $rx->items()->create(array_intersect_key($item, array_flip(['name', 'strength', 'form', 'route', 'dose_amount', 'dose_unit', 'frequency', 'timing', 'days', 'quantity', 'instructions']))
                    + ['medicine_id' => $medicine?->id, 'position' => ++$position]);
            }
        });

        return redirect()->route('prescriptions.show', ['id' => $rx->id])->with('success', __('The set :name was added.', ['name' => $set->name]));
    }

    /** Keep what is written as a set of the doctor, to add it again with one click. */
    public function saveSet(Request $request, int $id): RedirectResponse
    {
        Gate::authorize('prescriptions.manage');
        $rx = Prescription::with('items')->findOrFail($id);
        abort_unless($this->writes($request, $rx), 403);
        $name = $request->validate(['name' => ['required', 'string', 'max:120']])['name'];
        if ($rx->items->isEmpty()) {
            throw new UserError(__('Add at least one medicine.'));
        }
        PrescriptionSet::updateOrCreate(['doctor_id' => $rx->doctor_id, 'name' => $name], ['items' => $rx->items->map(fn (PrescriptionItem $i) => $i->only([
            'medicine_id', 'name', 'strength', 'form', 'route', 'dose_amount', 'dose_unit', 'frequency', 'timing', 'days', 'quantity', 'instructions']))->values()->all()]);

        return back()->with('success', __('Saved as the set :name.', ['name' => $name]));
    }

    public function deleteSet(Request $request, int $set): RedirectResponse
    {
        Gate::authorize('prescriptions.manage');
        $doctor = Prescribing::doctorOf($request->user()->id);
        PrescriptionSet::where('doctor_id', $doctor?->id)->findOrFail($set)->delete();

        return back()->with('success', __('The set was deleted.'));
    }

    public function sign(Request $request, int $id): RedirectResponse
    {
        $rx = $this->draft($request, $id);
        $request->validate(['password' => ['required', 'string'], 'reasons' => ['nullable', 'array'], 'reasons.*' => ['nullable', 'string', 'max:255']]);
        if (! Hash::check((string) $request->input('password'), (string) $request->user()->password)) {
            throw new UserError(__('The password is not right. The prescription is not signed.'));
        }
        $rx = Prescribing::sign($rx, (array) $request->input('reasons', []), $request->user()->id);

        return redirect()->route('prescriptions.show', ['id' => $rx->id])->with('success', __('Prescription :number is signed.', ['number' => $rx->number]));
    }

    public function cancel(Request $request, int $id): RedirectResponse
    {
        Gate::authorize('prescriptions.manage');
        $rx = Prescription::findOrFail($id);
        abort_unless($rx->isSigned() && $this->writes($request, $rx), 403);
        $reason = $request->validate(['reason' => ['required', 'string', 'max:255']])['reason'];
        Prescribing::cancel($rx, $reason, $request->user()->id);

        return redirect()->route('prescriptions.show', ['id' => $rx->id])->with('success', __('The prescription is cancelled.'));
    }

    /** The paper: A5, the letterhead of the clinic, the directions in the language asked for, the QR code. */
    public function print(Request $request, int $id): View
    {
        Gate::authorize('prescriptions.view');
        $rx = Prescription::with(['patient', 'doctor.user', 'items', 'encounter'])->findOrFail($id);
        abort_unless($rx->isSigned(), 404);
        $lang = in_array($request->query('lang'), ['en', 'fa', 'ps'], true) ? $request->query('lang') : (in_array(app()->getLocale(), ['fa', 'ps'], true) ? app()->getLocale() : 'en');
        audit('printed', $rx);

        return view('prescriptions.print', ['rx' => $rx, 'patient' => $rx->patient, 'lang' => $lang, 'qr' => Qr::svg($rx->verifyUrl(), 110)]);
    }

    /** What the box of the medicine asks as letters are typed: generic names and brands. */
    public function medicines(Request $request): JsonResponse
    {
        Gate::authorize('prescriptions.view');
        $term = trim((string) $request->query('q', ''));
        if (mb_strlen($term) < 2) {
            return response()->json(['results' => []]);
        }
        $rows = Medicine::where('is_active', true)->where('withdrawn', false)->search($term)->orderBy('name')->orderBy('form')->limit(20)->get();

        return response()->json(['results' => $rows->map(fn (Medicine $m) => [
            'id' => $m->id, 'name' => $m->name, 'strength' => $m->strength, 'form' => $m->form, 'route' => $m->route, 'label' => $m->label(),
            'brands' => self::brandsMatching($m->brands, $term), 'controlled' => $m->controlled,
            'unit' => match ($m->form) { 'tablet', 'vaginal tablet' => 'tablet', 'capsule' => 'capsule', 'syrup', 'suspension' => 'ml', 'drops', 'eye drops', 'ear drops' => 'drop',
                'inhaler' => 'puff', 'nasal spray' => 'spray', 'sachet' => 'sachet', 'suppository' => 'suppository', 'cream', 'ointment', 'gel', 'lotion', 'shampoo' => 'application',
                'injection', 'infusion' => 'ml', default => null },
        ])->values()]);
    }

    /** Anyone with the QR code (a pharmacist) sees whether the prescription is real, still good, and what it holds. */
    public function verify(string $token): View
    {
        $rx = Prescription::with(['items', 'doctor.user', 'patient'])->where('verify_token', $token)->first();
        abort_unless($rx && $rx->status !== Prescription::DRAFT, 404);
        $words = preg_split('/\s+/u', trim($rx->patient->name));

        return view('prescriptions.verify', [
            'rx' => $rx,
            'initials' => implode(' ', array_map(fn ($w) => mb_substr($w, 0, 1).'.', $words)),
            'genuine' => $rx->signatureHolds(),
        ]);
    }

    private function itemData(Request $request): array
    {
        $data = $request->validate([
            'medicine_id' => ['nullable', 'integer', Rule::exists('medicines', 'id')],
            'name' => ['required', 'string', 'max:150'],
            'strength' => ['nullable', 'string', 'max:80'],
            'form' => ['nullable', 'string', 'max:30'],
            'route' => ['nullable', 'string', 'max:20'],
            'dose_amount' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'dose_unit' => ['nullable', Rule::in(array_keys(Prescribing::UNITS))],
            'frequency' => ['nullable', Rule::in(array_keys(Prescribing::FREQUENCIES))],
            'timing' => ['nullable', Rule::in(array_keys(Prescribing::TIMINGS))],
            'days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'quantity' => ['nullable', 'string', 'max:40'],
            'instructions' => ['nullable', 'string', 'max:255'],
        ]);
        // a medicine from the list keeps the words of the list
        if (! empty($data['medicine_id']) && ($m = Medicine::find($data['medicine_id']))) {
            if ($m->withdrawn || ! $m->is_active) {
                throw new UserError(__(':name cannot be prescribed: it was taken off the list.', ['name' => $m->label()]));
            }
            $data = array_merge($data, ['name' => $m->name, 'strength' => $m->strength, 'form' => $m->form, 'route' => $data['route'] ?? $m->route]);
        }
        $data['quantity'] = ($data['quantity'] ?? null) ?: Prescribing::quantity(isset($data['dose_amount']) ? (float) $data['dose_amount'] : null, $data['dose_unit'] ?? null, $data['frequency'] ?? null, $data['days'] ?? null);

        return $data;
    }

    private function draft(Request $request, int $id): Prescription
    {
        Gate::authorize('prescriptions.manage');
        $rx = Prescription::findOrFail($id);
        if (! $rx->isDraft()) {
            throw new UserError(__('This prescription is signed and locked. Cancel it and write a new one.'));
        }
        abort_unless($this->writes($request, $rx), 403);

        return $rx;
    }

    private function writes(Request $request, Prescription $rx): bool
    {
        return $request->user()->can('prescriptions.manage') && Prescribing::doctorOf($request->user()->id)?->id === $rx->doctor_id;
    }

    /** The brands of a medicine that match what was typed, so "Panadol" shows why paracetamol was found. */
    private static function brandsMatching(?string $brands, string $term): ?string
    {
        if (! $brands) {
            return null;
        }
        $found = [];
        foreach (explode(' | ', $brands) as $part) {
            foreach (explode(', ', substr($part, 4)) as $brand) {
                foreach (preg_split('/\s+/u', $term) as $word) {
                    if ($word !== '' && mb_stripos($brand, $word) !== false) {
                        $found[] = $brand.' ('.substr($part, 0, 2).')';
                    }
                }
            }
        }

        return $found ? implode(', ', array_unique($found)) : null;
    }
}
