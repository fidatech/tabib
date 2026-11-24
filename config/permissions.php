<?php

/*
| What a role can be allowed to do. Every module has two switches:
| "view" (open and read) and "manage" (add, change, cancel).
| The Administrator role (the owner) always has everything.
| Modules are added here as they are built; the clinic modules are listed already so the
| usual roles of a clinic can be made from the first day.
*/

return [
    'patients' => 'Patients: registration and demographics',
    'appointments' => 'Appointments, the calendar and the waiting queue',
    'clinical' => 'Patient history, visits and clinical notes',
    'prescriptions' => 'Prescriptions (manage = write and sign)',
    'billing' => 'Services, invoices and payments',
    'reports' => 'Reports',
    'branches' => 'Branches of the clinic',
    'users' => 'Users and roles',
    'settings' => 'Settings of the clinic',
    'audit' => 'Audit log',
];
