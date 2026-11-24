<?php

namespace App\Http\Controllers;

use App\Exceptions\UserError;
use App\Models\Branch;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BranchController extends CrudController
{
    protected string $model = Branch::class;

    protected string $route = 'branches';

    protected string $permission = 'branches';

    protected string $title = 'Branches';

    protected string $singular = 'Branch';

    protected string $icon = 'building';

    protected array $search = ['name', 'name_local', 'code', 'city'];

    protected string $orderBy = 'id';

    protected function fields(?Model $record): array
    {
        $fields = [
            ['name' => 'name', 'label' => 'Branch name', 'rules' => ['required', 'string', 'max:150']],
            ['name' => 'name_local', 'label' => 'Name in Dari or Pashto', 'rules' => ['nullable', 'string', 'max:150']],
            ['name' => 'code', 'label' => 'Short code', 'rules' => ['required', 'string', 'max:20', 'alpha_dash', Rule::unique('branches')->ignore($record?->id)],
                'help' => 'A few letters that mark the papers of this branch, for example HRT'],
        ];

        // where it is: the country first, then its province, then the city
        $countries = array_combine(config('places.countries'), config('places.countries'));
        $country = old('country', $record?->country ?: 'Afghanistan');
        $provinces = config('places.provinces')[$country] ?? [];
        $fields[] = ['name' => 'country', 'label' => 'Country', 'type' => 'select', 'default' => 'Afghanistan', 'value' => $record ? ($record->country ?: 'Afghanistan') : 'Afghanistan', 'search' => true,
            'options' => $countries, 'rules' => ['nullable', Rule::in(config('places.countries'))]];
        $fields[] = ['name' => 'province', 'label' => 'Province', 'type' => 'select', 'options' => array_combine($provinces, $provinces),
            'attrs' => ['data-follows' => '#f-country', 'data-choices' => json_encode(config('places.provinces')), 'data-value' => (string) old('province', $record?->province), 'data-plain' => '1'],
            'rules' => ['nullable', 'string', 'max:100']];
        $fields[] = ['name' => 'city', 'label' => 'City', 'rules' => ['nullable', 'string', 'max:100']];
        $fields[] = ['name' => 'representative_name', 'label' => 'Person in charge', 'rules' => ['nullable', 'string', 'max:150']];
        $fields[] = ['name' => 'phone', 'label' => 'Phone', 'rules' => ['nullable', 'string', 'max:50']];
        $fields[] = ['name' => 'address', 'label' => 'Address', 'col' => 12, 'rules' => ['nullable', 'string', 'max:255']];

        if (! $record?->isMain()) {
            $fields[] = ['name' => 'is_active', 'label' => 'Active', 'type' => 'checkbox', 'default' => true];
        }

        // a new branch may get its first user on the same page
        if (! $record) {
            Permissions::sync();
            $roles = Role::where('name', '!=', User::ADMIN_ROLE)->orderBy('name')->pluck('name', 'name')->all();
            $fields[] = ['name' => 'user_heading', 'label' => 'User of the branch', 'type' => 'heading', 'readonly' => true, 'help' => 'The first user who signs in for this branch. It can also be added later on the Users page.'];
            $fields[] = ['name' => 'user_name', 'label' => 'Full name', 'virtual' => true, 'rules' => ['nullable', 'required_with:user_email', 'string', 'max:255']];
            $fields[] = ['name' => 'user_email', 'label' => 'Email address', 'type' => 'email', 'virtual' => true, 'rules' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')]];
            $fields[] = ['name' => 'user_password', 'label' => 'Password', 'type' => 'password', 'virtual' => true, 'rules' => ['nullable', 'required_with:user_email', Password::min(8)], 'help' => 'At least 8 characters'];
            $fields[] = ['name' => 'user_role', 'label' => 'Role', 'type' => 'select', 'virtual' => true, 'default' => 'Clinic Admin', 'options' => $roles, 'rules' => ['nullable', 'required_with:user_email', Rule::in(array_keys($roles))]];
        }

        return $fields;
    }

    protected function columns(): array
    {
        return [
            'Branch name' => fn (Branch $b) => $b->name,
            'Short code' => fn (Branch $b) => $b->code,
            'Branch type' => fn (Branch $b) => __(Branch::TYPES[$b->type] ?? $b->type),
            'City' => fn (Branch $b) => $b->city,
            'Person in charge' => fn (Branch $b) => $b->representative_name,
            'Active' => fn (Branch $b) => $b->is_active ? __('Yes') : __('No'),
        ];
    }

    /** Codes are kept in capital letters, so "hrt" and "HRT" are the same code. */
    protected function save(Request $request, ?Model $record): Model
    {
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);

        return parent::save($request, $record);
    }

    protected function beforeSave(array $data, ?Model $record, Request $request): array
    {
        $data['country'] = ($data['country'] ?? null) ?: 'Afghanistan';

        if ($record?->isMain()) {
            return $data;
        }
        $data['type'] = Branch::DEPENDENT;

        // the software licence says how many more branches the clinic may have
        $limit = tenant()?->max_branches;
        $wantsSeat = ! empty($data['is_active']);
        $hadSeat = $record && $record->is_active;
        if ($limit !== null && $wantsSeat && ! $hadSeat) {
            $used = Branch::where('type', Branch::DEPENDENT)->where('is_active', true)->count();
            if ($used >= $limit) {
                throw new UserError(__('Your plan allows :count more branches. Ask Fida Technologies to add more.', ['count' => $limit]));
            }
        }

        return $data;
    }

    protected function afterSave(Model $record, array $virtual, Request $request, bool $created): void
    {
        if ($created && ! empty($virtual['user_email'])) {
            $user = User::create([
                'name' => $virtual['user_name'], 'email' => strtolower($virtual['user_email']), 'password' => $virtual['user_password'],
                'branch_id' => $record->id, 'is_active' => true,
            ]);
            $user->syncRoles([$virtual['user_role']]);
            audit('created', $user, $user->email.' ('.$record->name.')');
        }
    }

    protected function deleteProblem(Model $record): ?string
    {
        if ($record->isMain()) {
            return __('The main branch cannot be deleted.');
        }
        if ($record->users()->exists()) {
            return __('This branch still has users. Move them to another branch first, or switch the branch off.');
        }

        return null;
    }
}
