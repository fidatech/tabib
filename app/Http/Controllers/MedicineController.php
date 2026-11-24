<?php

namespace App\Http\Controllers;

use App\Models\Medicine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * The medicines of the clinic: the shipped list and the clinic's own. The clinic switches
 * medicines off, adds its own, and writes the dose limits its pharmacist approves.
 */
class MedicineController extends CrudController
{
    protected string $model = Medicine::class;

    protected string $route = 'medicines';

    protected string $permission = 'prescriptions';

    protected string $title = 'Medicines';

    protected string $singular = 'Medicine';

    protected string $icon = 'pills';

    protected array $search = [];

    protected string $orderBy = 'name';

    protected function query(Request $request): Builder
    {
        return Medicine::query()->search((string) $request->query('q', ''))
            ->when($request->query('show') === 'off', fn ($q) => $q->where('is_active', false))
            ->when($request->query('show') === 'own', fn ($q) => $q->where('is_custom', true));
    }

    protected function filters(Request $request): array
    {
        return [['name' => 'show', 'label' => __('All medicines'), 'options' => ['own' => __('Added by the clinic'), 'off' => __('Switched off')], 'value' => (string) $request->query('show', '')]];
    }

    protected function fields(?Model $record): array
    {
        /** @var Medicine|null $record */
        $fromList = $record && ! $record->is_custom;
        $forms = array_combine(Medicine::FORMS, array_map(fn ($f) => __($f), Medicine::FORMS));

        return [
            ['name' => 'name', 'label' => 'Generic name', 'rules' => ['required', 'string', 'max:150'], 'readonly' => $fromList],
            ['name' => 'strength', 'label' => 'Strength', 'rules' => ['nullable', 'string', 'max:80'], 'readonly' => $fromList, 'help' => 'For example: 500 mg, 250 mg/5 ml'],
            ['name' => 'form', 'label' => 'Form', 'type' => 'select', 'options' => $forms, 'rules' => ['required', Rule::in(Medicine::FORMS)], 'readonly' => $fromList],
            ['name' => 'class', 'label' => 'Class', 'rules' => ['nullable', 'string', 'max:60'], 'readonly' => $fromList, 'help' => 'Matched against allergies: penicillin, cephalosporin, nsaid, sulfonamide ...'],
            ['name' => 'brands', 'label' => 'Brand names', 'type' => 'textarea', 'col' => 12, 'rules' => ['nullable', 'string', 'max:2000'], 'help' => 'Brands sold here, so staff find the medicine by them.'],
            ['name' => 'mg_per_unit', 'label' => 'mg in one tablet, capsule or ml', 'type' => 'decimal', 'rules' => ['nullable', 'numeric', 'min:0']],
            ['name' => 'child_max_mg_per_kg_dose', 'label' => 'Highest dose for a child (mg/kg a dose)', 'type' => 'decimal', 'rules' => ['nullable', 'numeric', 'min:0'],
                'help' => 'Approved by your pharmacist. Empty: no check.'],
            ['name' => 'max_daily_mg', 'label' => 'Highest dose in a day (mg)', 'type' => 'decimal', 'rules' => ['nullable', 'numeric', 'min:0'], 'help' => 'Approved by your pharmacist. Empty: no check.'],
            ['name' => 'controlled', 'label' => 'Controlled medicine', 'type' => 'checkbox', 'readonly' => $fromList],
            ['name' => 'is_active', 'label' => 'Can be prescribed', 'type' => 'checkbox', 'default' => true],
        ];
    }

    protected function columns(): array
    {
        return [
            'Medicine' => fn (Medicine $m) => $m->label(),
            'Class' => fn (Medicine $m) => $m->class ? __($m->class) : '',
            'Brand names' => fn (Medicine $m) => Str::limit((string) $m->brands, 70),
            'Can be prescribed' => fn (Medicine $m) => $m->is_active ? __('Yes') : __('No'),
        ];
    }

    protected function beforeSave(array $data, ?Model $record, Request $request): array
    {
        if (! $record) {
            $data['is_custom'] = true;
            $data['code'] = 'custom-'.Str::slug($data['name'].' '.($data['strength'] ?? '').' '.$data['form']).'-'.Str::lower(Str::random(4));
            $data['route'] = match ($data['form']) { 'injection', 'infusion' => 'injection', 'cream', 'ointment', 'gel', 'lotion', 'shampoo' => 'topical', 'eye drops' => 'eye',
                'ear drops' => 'ear', 'nasal spray' => 'nasal', 'inhaler', 'nebuliser solution' => 'inhaled', 'suppository' => 'rectal', 'vaginal tablet' => 'vaginal', default => 'oral' };
        }

        return $data;
    }

    protected function deleteProblem(Model $record): ?string
    {
        return ! $record->is_custom || \App\Models\PrescriptionItem::where('medicine_id', $record->id)->exists()
            ? __('This medicine is kept. Switch it off instead.')
            : null;
    }
}
