<?php

/*
|--------------------------------------------------------------------------
| Sidebar menu of a clinic
|--------------------------------------------------------------------------
|
|   ['heading' => 'Text']
|   ['label' => 'Text', 'icon' => 'gauge-high', 'route' => 'dashboard']
|   ['label' => 'Text', 'icon' => 'users', 'children' => [
|       ['label' => 'Text', 'route' => 'users.index', 'active' => 'users.*', 'can' => 'users.view'],
|   ]]
|
| icon    a Font Awesome solid icon name; after adding one run: php tools/build-assets.php
| params  route parameters (and query values) of the link
| active  route-name pattern that highlights the entry (defaults to the route)
| can     permission needed to see the entry
| badge   a count shown in red beside the entry when it is not zero (see App\Support\Menu::badge)
| url     instead of route: a page outside the system, opened in a new tab
|
*/

return [
    ['label' => 'Dashboard', 'icon' => 'gauge-high', 'route' => 'dashboard'],

    // the clinic modules (patients, appointments, visits, prescriptions, billing, reports) are added here as they are built

    ['label' => 'Branches', 'icon' => 'code-branch', 'route' => 'branches.index', 'active' => 'branches.*', 'can' => 'branches.view'],

    ['label' => 'Administration', 'icon' => 'user-shield', 'children' => [
        ['label' => 'Users', 'route' => 'users.index', 'active' => 'users.*', 'can' => 'users.view'],
        ['label' => 'Roles', 'route' => 'roles.index', 'active' => 'roles.*', 'can' => 'users.view'],
        ['label' => 'Permissions', 'route' => 'permissions.index', 'can' => 'users.view'],
        ['label' => 'Audit Log', 'route' => 'audit.index', 'can' => 'audit.view'],
    ]],

    ['label' => 'Configurations', 'icon' => 'gear', 'children' => [
        ['label' => 'Clinic Settings', 'route' => 'settings.index', 'active' => 'settings.*', 'can' => 'settings.view'],
    ]],

    // support, subscription and invoices open from the menu of the profile in the navbar
];
