<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;
use App\Services\Sequence;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** The patients of the clinic: registration, the search the front desk uses all day, and the page of one patient. */
class PatientController extends CrudController
{
    protected string $model = Patient::class;

    protected string $route = 'patients';

    protected string $permission = 'patients';

    protected string $title = 'Patients';

    protected string $singular = 'Patient';

    protected string $icon = 'hospital-user';

    // the search of the list is the patient search (name, father, phone, record number, identity card)
    protected array $search = [];

    protected string $orderBy = 'id';

    protected string $orderDirection = 'desc';

    protected function query(Request $request): Builder
    {
        return Patient::query()->search((string) $request->query('q', ''));
    }

    protected function fields(?Model $record): array
    {
        /** @var Patient|null $record */
        $genders = array_map(fn (string $g) => __($g), Patient::GENDERS);

        return [
            ['name' => 'name', 'label' => 'Full name', 'rules' => ['required', 'string', 'max:150']],
            ['name' => 'father_name', 'label' => "Father's name", 'rules' => ['nullable', 'string', 'max:150']],
            ['name' => 'gender', 'label' => 'Gender', 'type' => 'select', 'options' => $genders, 'rules' => ['required', Rule::in(array_keys(Patient::GENDERS))], 'col' => 4],
            ['name' => 'date_of_birth', 'label' => 'Date of birth', 'type' => 'date', 'rules' => ['nullable', 'date', 'before_or_equal:'.today_iso(), 'after:1900-01-01'], 'col' => 4],
            ['name' => 'age', 'label' => 'Or age in years', 'type' => 'number', 'virtual' => true, 'value' => $record?->dob_estimated ? $record->age() : null,
                'rules' => ['nullable', 'integer', 'min:0', 'max:130'], 'col' => 4, 'help' => 'When the date of birth is not known'],
            ['name' => 'phone', 'label' => 'Phone', 'rules' => ['nullable', 'string', 'max:40'], 'col' => 4],
            ['name' => 'phone2', 'label' => 'Second phone', 'rules' => ['nullable', 'string', 'max:40'], 'col' => 4],
            ['name' => 'tazkira', 'label' => 'Tazkira number', 'rules' => ['nullable', 'string', 'max:40'], 'col' => 4],
            ['name' => 'blood_group', 'label' => 'Blood group', 'type' => 'select', 'options' => ['' => '—'] + array_combine(Patient::BLOOD_GROUPS, Patient::BLOOD_GROUPS),
                'rules' => ['nullable', Rule::in(Patient::BLOOD_GROUPS)], 'col' => 4],
            ['name' => 'province', 'label' => 'Province', 'rules' => ['nullable', 'string', 'max:100'], 'col' => 4],
            ['name' => 'address', 'label' => 'Address', 'rules' => ['nullable', 'string', 'max:255'], 'col' => 4],
            ['name' => 'emergency_name', 'label' => 'Emergency contact', 'rules' => ['nullable', 'string', 'max:150']],
            ['name' => 'emergency_phone', 'label' => 'Emergency phone', 'rules' => ['nullable', 'string', 'max:40']],
            ['name' => 'allergies', 'label' => 'Known allergies', 'type' => 'textarea', 'col' => 12, 'rules' => ['nullable', 'string', 'max:500'],
                'help' => 'Shown in red on every page of the patient. Write "None known" when the patient has none.'],
            ['name' => 'notes', 'label' => 'Notes', 'type' => 'textarea', 'col' => 12, 'rules' => ['nullable', 'string', 'max:2000']],
        ];
    }

    protected function columns(): array
    {
        return [
            'Record number' => fn (Patient $p) => $p->number,
            'Full name' => fn (Patient $p) => $p->name,
            "Father's name" => fn (Patient $p) => $p->father_name,
            'Gender' => fn (Patient $p) => __(Patient::GENDERS[$p->gender] ?? $p->gender),
            'Age' => fn (Patient $p) => $p->ageText(),
            'Phone' => fn (Patient $p) => $p->phone,
        ];
    }

    protected function rowLinks(Model $record): array
    {
        $links = [['label' => __('Open'), 'url' => route('patients.show', ['id' => $record->getKey()]), 'icon' => 'folder-open']];
        if (request()->user()->can('appointments.manage')) {
            $links[] = ['label' => __('Book'), 'url' => route('appointments.create', ['patient' => $record->getKey()]), 'icon' => 'calendar-plus'];
        }

        return $links;
    }

    protected function beforeSave(array $data, ?Model $record, Request $request): array
    {
        // only the age is known: the birthday is taken as the 1st of January of that year, and marked as estimated
        $age = $request->input('age');
        if (empty($data['date_of_birth']) && $age !== null && $age !== '') {
            $data['date_of_birth'] = Carbon::parse(today_iso())->subYears((int) $age)->startOfYear()->toDateString();
            $data['dob_estimated'] = true;
        } elseif (! empty($data['date_of_birth'])) {
            $data['dob_estimated'] = $record && $record->date_of_birth?->toDateString() === $data['date_of_birth'] ? $record->dob_estimated : false;
        }
        if (! $record) {
            $data['number'] = Sequence::next('P');
            $data['created_by'] = $request->user()->id;
        }

        return $data;
    }

    /** A new patient opens on his own page, where the next step (an appointment) is one click away. */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $response = parent::store($request);
        if ($response instanceof JsonResponse || ! $this->saved) {
            return $response;
        }

        return redirect()->route('patients.show', ['id' => $this->saved[0]->getKey()])->with('success', __('Patient :number registered.', ['number' => $this->saved[0]->number]));
    }

    protected function deleteProblem(Model $record): ?string
    {
        return $record->appointments()->exists()
            ? __('This patient has appointments: the record is kept. Correct it instead.')
            : null;
    }

    public function show(Request $request, int $id): View
    {
        Gate::authorize('patients.view');
        $patient = Patient::findOrFail($id);
        audit('viewed', $patient);

        $appointments = $patient->appointments()->with(['doctor.user', 'type'])->orderByDesc('starts_at')->get();
        $now = now();

        return view('patients.show', [
            'patient' => $patient,
            'upcoming' => $appointments->filter(fn (Appointment $a) => $a->isOpen() && $a->starts_at >= $now->copy()->startOfDay())->sortBy('starts_at')->values(),
            'past' => $appointments->reject(fn (Appointment $a) => $a->isOpen() && $a->starts_at >= $now->copy()->startOfDay())->values(),
            'doctors' => Doctor::active(),
        ]);
    }

    /** What the patient box of the appointment form asks as letters are typed: the first matches. */
    public function search(Request $request): JsonResponse
    {
        Gate::authorize('patients.view');
        $term = trim((string) $request->query('q', ''));
        if (mb_strlen($term) < 2) {
            return response()->json(['results' => []]);
        }
        $rows = Patient::search($term)->orderBy('name')->limit(15)->get(['id', 'number', 'name', 'father_name', 'phone', 'gender', 'date_of_birth', 'dob_estimated']);

        return response()->json(['results' => $rows->map(fn (Patient $p) => [
            'id' => $p->id,
            'text' => $p->name.($p->father_name ? ' '.__('son/daughter of').' '.$p->father_name : ''),
            'meta' => implode(' · ', array_filter([$p->number, $p->ageText(), $p->phone])),
        ])->all()]);
    }
}
