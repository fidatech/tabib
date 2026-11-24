<?php

namespace App\Support;

use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/** The usual jobs in a clinic. A clinic may change them or add its own. */
class Roles
{
    /** role => modules it may change; the modules after "view" it may only look at */
    public const DEFAULTS = [
        'Clinic Admin' => ['manage' => ['patients', 'appointments', 'billing', 'branches', 'users', 'settings'], 'view' => ['reports', 'audit']],
        // only a doctor writes and signs clinical notes and prescriptions
        'Doctor' => ['manage' => ['patients', 'appointments', 'clinical', 'prescriptions'], 'view' => ['billing', 'reports']],
        // enters vitals and history before the doctor; never signs
        'Nurse' => ['manage' => ['patients', 'appointments', 'clinical'], 'view' => ['prescriptions']],
        'Receptionist' => ['manage' => ['patients', 'appointments', 'billing'], 'view' => []],
        'Accountant' => ['manage' => ['billing'], 'view' => ['patients', 'appointments', 'reports']],
    ];

    /** A role that already exists keeps the permissions it has now. */
    public static function createDefaults(): void
    {
        Permissions::sync();
        $known = Permissions::all();

        Role::findOrCreate(User::ADMIN_ROLE, 'web');

        foreach (self::DEFAULTS as $name => $modules) {
            if (Role::where('name', $name)->where('guard_name', 'web')->exists()) {
                continue;
            }
            $permissions = [];
            foreach ($modules['manage'] as $module) {
                $permissions[] = $module.'.view';
                $permissions[] = $module.'.manage';
            }
            foreach ($modules['view'] as $module) {
                $permissions[] = $module.'.view';
            }
            Role::create(['name' => $name, 'guard_name' => 'web'])->syncPermissions(array_values(array_intersect($permissions, $known)));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
