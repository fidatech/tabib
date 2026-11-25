<?php

namespace App\Http\Controllers;

use App\Models\Encounter;
use App\Models\NoteTemplate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/** The note templates of the clinic: the starting words of a visit, per specialty. */
class NoteTemplateController extends CrudController
{
    protected string $model = NoteTemplate::class;

    protected string $route = 'note-templates';

    protected string $permission = 'clinical';

    protected string $title = 'Note templates';

    protected string $singular = 'Note template';

    protected string $icon = 'file-medical';

    protected array $search = ['name'];

    protected string $orderBy = 'name';

    protected function fields(?Model $record): array
    {
        $fields = [
            ['name' => 'name', 'label' => 'Name', 'rules' => ['required', 'string', 'max:100']],
            ['name' => 'kind', 'label' => 'Form', 'type' => 'select', 'options' => array_map(fn ($k) => __($k), NoteTemplate::KINDS), 'rules' => ['required', Rule::in(array_keys(NoteTemplate::KINDS))], 'default' => 'general',
                'help' => 'Dental adds the tooth chart; antenatal adds the pregnancy form with the due date.'],
        ];
        foreach (Encounter::PARTS as $part => $label) {
            $fields[] = ['name' => $part, 'label' => $label, 'type' => 'textarea', 'col' => 12, 'rules' => ['nullable', 'string', 'max:5000']];
        }
        $fields[] = ['name' => 'is_active', 'label' => 'In use', 'type' => 'checkbox', 'default' => true];

        return $fields;
    }

    protected function columns(): array
    {
        return [
            'Name' => fn (NoteTemplate $t) => $t->name,
            'Form' => fn (NoteTemplate $t) => __(NoteTemplate::KINDS[$t->kind] ?? $t->kind),
            'In use' => fn (NoteTemplate $t) => $t->is_active ? __('Yes') : __('No'),
        ];
    }

    protected function deleteProblem(Model $record): ?string
    {
        return Encounter::where('template_id', $record->id)->exists() ? __('Visits were written with this template. Switch it off instead.') : null;
    }
}
