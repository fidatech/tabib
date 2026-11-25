<?php

namespace App\Http\Controllers;

use App\Exceptions\UserError;
use App\Models\Doctor;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

/**
 * The doctors of the clinic. Adding a doctor makes the user he signs in with too (with the
 * Doctor role), so the owner fills in one form; an existing user can be made a doctor as well.
 */
class DoctorController extends CrudController
{
    protected string $model = Doctor::class;

    protected string $route = 'doctors';

    protected string $permission = 'users';

    protected string $title = 'Doctors';

    protected string $singular = 'Doctor';

    protected string $icon = 'user-doctor';

    protected array $search = [];

    protected array $with = ['user'];

    protected string $orderBy = 'id';

    protected function query(Request $request): Builder
    {
        $term = trim((string) $request->query('q', ''));

        return Doctor::query()->when($term !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w
            ->where('specialty', 'like', '%'.$term.'%')
            ->orWhereHas('user', fn (Builder $u) => $u->where('name', 'like', '%'.$term.'%')->orWhere('email', 'like', '%'.$term.'%'))));
    }

    protected function fields(?Model $record): array
    {
        /** @var Doctor|null $record */
        $user = $record?->user;
        $colours = [];
        foreach (array_keys(Doctor::COLOURS) as $colour) {
            $colours[$colour] = __(ucfirst($colour));
        }

        return [
            ['name' => 'person_heading', 'label' => 'The doctor', 'type' => 'heading', 'readonly' => true, 'icon' => 'user-doctor',
                'help' => $record ? 'Name and sign-in of the doctor.' : 'The doctor signs in with this email and password.'],
            ['name' => 'name', 'label' => 'Full name', 'virtual' => true, 'value' => $user?->name, 'rules' => ['required', 'string', 'max:255']],
            // a new doctor may be a user of the clinic already (found by his email); a doctor's email cannot become another user's
            ['name' => 'email', 'label' => 'Email address', 'type' => 'email', 'virtual' => true, 'value' => $user?->email,
                'rules' => array_values(array_filter(['required', 'email', 'max:255', $record ? Rule::unique('users', 'email')->ignore($user?->id) : null]))],
            ['name' => 'phone', 'label' => 'Phone', 'virtual' => true, 'value' => $user?->phone, 'rules' => ['nullable', 'string', 'max:40']],
            ['name' => 'password', 'label' => 'Password', 'type' => 'password', 'virtual' => true,
                'rules' => ['nullable', Password::min(8)],
                'help' => $record ? 'Leave empty to keep the current password' : 'At least 8 characters. Not needed when the email is already a user of the clinic.'],
            ['name' => 'work_heading', 'label' => 'Work', 'type' => 'heading', 'readonly' => true, 'icon' => 'stethoscope'],
            ['name' => 'specialty', 'label' => 'Specialty', 'rules' => ['nullable', 'string', 'max:120'], 'help' => 'For example: General practitioner, Dentist, Gynecologist'],
            ['name' => 'licence_no', 'label' => 'Licence number of the doctor', 'rules' => ['nullable', 'string', 'max:60'], 'help' => 'Printed on prescriptions'],
            ['name' => 'fee', 'label' => 'Consultation fee', 'type' => 'decimal', 'default' => '0', 'rules' => ['required', 'numeric', 'min:0']],
            ['name' => 'room', 'label' => 'Room', 'rules' => ['nullable', 'string', 'max:60']],
            ['name' => 'colour', 'label' => 'Colour on the calendar', 'type' => 'select', 'default' => $this->freeColour(), 'options' => $colours, 'rules' => ['required', Rule::in(array_keys(Doctor::COLOURS))]],
            ['name' => 'is_active', 'label' => 'Sees patients', 'type' => 'checkbox', 'default' => true],
        ];
    }

    protected function columns(): array
    {
        return [
            'Full name' => fn (Doctor $d) => $d->name(),
            'Specialty' => fn (Doctor $d) => $d->specialty,
            'Licence number of the doctor' => fn (Doctor $d) => $d->licence_no,
            'Consultation fee' => fn (Doctor $d) => money($d->fee),
            'Room' => fn (Doctor $d) => $d->room,
            'Sees patients' => fn (Doctor $d) => $d->is_active ? __('Yes') : __('No'),
        ];
    }

    /**
     * The user of the doctor is made or changed here, in the same transaction as the doctor:
     * a new email makes a new user with the Doctor role; the email of a user who is already in the
     * clinic (a nurse who became a doctor, the owner who is a doctor) gives that user the profile.
     */
    protected function beforeSave(array $data, ?Model $record, Request $request): array
    {
        /** @var Doctor|null $record */
        $email = strtolower(trim((string) $request->input('email')));
        $user = $record?->user ?? User::where('email', $email)->first();

        if ($user && ! $record && Doctor::where('user_id', $user->id)->exists()) {
            throw new UserError(__('This person is a doctor of the clinic already.'));
        }
        if (! $user && ! $request->filled('password')) {
            throw new UserError(__('Write a password for the new doctor.'));
        }
        // the plan of the clinic says how many doctors may work in it
        $limit = tenant()?->max_doctors;
        if ($limit !== null && ! empty($data['is_active']) && ! ($record && $record->is_active) && Doctor::where('is_active', true)->count() >= $limit) {
            throw new UserError(__('Your plan allows :count doctors. Ask Fida Technologies to add more.', ['count' => $limit]));
        }

        $user ??= new User(['branch_id' => current_branch()->id, 'is_active' => true, 'calendar' => 'shamsi']);
        $user->fill(['name' => $request->input('name'), 'email' => $email, 'phone' => $request->input('phone')]);
        if ($request->filled('password')) {
            $user->password = $request->input('password');
        }
        $user->save();
        if (! $user->hasRole('Doctor') && ! $user->isAdmin()) {
            Role::findOrCreate('Doctor', 'web');
            $user->assignRole('Doctor');
        }
        $data['user_id'] = $user->id;

        return $data;
    }

    protected function deleteProblem(Model $record): ?string
    {
        return $record->appointments()->exists()
            ? __('This doctor has appointments. Switch him off instead, so his history stays.')
            : null;
    }

    /** A new doctor gets a colour that nobody uses yet, while there is one. */
    private function freeColour(): string
    {
        $used = Doctor::pluck('colour')->all();

        return collect(array_keys(Doctor::COLOURS))->first(fn (string $c) => ! in_array($c, $used, true)) ?? 'teal';
    }

    protected function rowLinks(Model $record): array
    {
        return request()->user()->can('appointments.manage')
            ? [['label' => __('Hours and days off'), 'url' => route('doctors.schedule', ['id' => $record->getKey()]), 'icon' => 'business-time']]
            : [];
    }
}
