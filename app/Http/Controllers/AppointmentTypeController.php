<?php

namespace App\Http\Controllers;

use App\Models\AppointmentType;
use Illuminate\Database\Eloquent\Model;

/** The kinds of appointment the clinic gives: how long each takes and what it costs. */
class AppointmentTypeController extends CrudController
{
    protected string $model = AppointmentType::class;

    protected string $route = 'appointment-types';

    protected string $permission = 'settings';

    protected string $title = 'Appointment types';

    protected string $singular = 'Appointment type';

    protected string $icon = 'calendar-check';

    protected array $search = ['name', 'name_local'];

    protected function fields(?Model $record): array
    {
        return [
            ['name' => 'name', 'label' => 'Name', 'rules' => ['required', 'string', 'max:120'], 'help' => 'For example: First visit, Follow-up, Dressing, Injection'],
            ['name' => 'name_local', 'label' => 'Name in Dari or Pashto', 'rules' => ['nullable', 'string', 'max:120']],
            ['name' => 'minutes', 'label' => 'Minutes', 'type' => 'number', 'default' => setting('slot_minutes', '15'), 'rules' => ['required', 'integer', 'min:5', 'max:480']],
            ['name' => 'price', 'label' => 'Price', 'type' => 'decimal', 'default' => '0', 'rules' => ['required', 'numeric', 'min:0']],
            ['name' => 'is_active', 'label' => 'Can be booked', 'type' => 'checkbox', 'default' => true],
        ];
    }

    protected function columns(): array
    {
        return [
            'Name' => fn (AppointmentType $t) => $t->label(),
            'Minutes' => fn (AppointmentType $t) => $t->minutes,
            'Price' => fn (AppointmentType $t) => money($t->price),
            'Can be booked' => fn (AppointmentType $t) => $t->is_active ? __('Yes') : __('No'),
        ];
    }

    protected function deleteProblem(Model $record): ?string
    {
        return \App\Models\Appointment::where('appointment_type_id', $record->id)->exists()
            ? __('Appointments of this type exist. Switch it off instead.')
            : null;
    }
}
