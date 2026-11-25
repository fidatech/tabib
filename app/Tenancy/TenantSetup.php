<?php

namespace App\Tenancy;

use App\Models\Branch;
use App\Models\Central\Tenant;
use App\Models\Setting;
use App\Models\User;
use App\Support\Permissions;
use App\Support\Roles;

/** The first records of a new clinic: roles, the main branch, the owner and the settings. */
class TenantSetup
{
    /** Records every clinic needs and that a new version may add to: the permissions and the list of medicines. */
    public function baseData(): void
    {
        Permissions::sync();
        \App\Services\Prescribing::syncMedicines();
        \App\Models\NoteTemplate::seedDefaults();
    }

    public function run(Tenant $tenant, array $data): void
    {
        $this->baseData();
        Roles::createDefaults();

        $main = Branch::create([
            'name' => $data['main_branch_name'] ?? 'Main branch',
            'code' => 'MAIN',
            'type' => Branch::MAIN,
            'province' => $data['province'] ?? null,
            'city' => $data['main_branch_city'] ?? null,
        ]);

        $owner = User::create([
            'name' => $data['owner_name'],
            'email' => strtolower($data['owner_email']),
            'phone' => $data['owner_phone'] ?? null,
            'password' => $data['owner_password'],
            'branch_id' => $main->id,
            'calendar' => 'shamsi',
        ]);
        $owner->assignRole(User::ADMIN_ROLE);

        foreach (Setting::DEFAULTS + ['company_name' => $tenant->name, 'company_name_local' => $tenant->name_local, 'licence_no' => $tenant->licence_no] as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
