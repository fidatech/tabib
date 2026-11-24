<?php

/*
|--------------------------------------------------------------------------
| The row of daily work under the navbar
|--------------------------------------------------------------------------
|
| Buttons shown on every page of a clinic: the front desk and the doctors use them all day
| (new appointment, register a patient, the queue). An entry is written like an entry of
| config/menu.php (label, icon, route, params, active, can), with 'tone' for the colour of its icon.
| The row is hidden while it is empty. Entries are added as the clinic modules are built.
|
*/

return [
    ['label' => 'New appointment', 'icon' => 'calendar-plus', 'tone' => 'primary', 'route' => 'appointments.create', 'active' => 'appointments.create', 'can' => 'appointments.manage'],
    ['label' => 'Walk-in', 'icon' => 'person-walking', 'tone' => 'warning', 'route' => 'appointments.create', 'params' => ['walk_in' => 1], 'active' => 'appointments.create', 'can' => 'appointments.manage'],
    ['label' => 'Register a patient', 'icon' => 'user-plus', 'tone' => 'success', 'route' => 'patients.create', 'active' => 'patients.create', 'can' => 'patients.manage'],
    ['label' => 'Queue', 'icon' => 'list-ol', 'tone' => 'purple', 'route' => 'queue.index', 'can' => 'appointments.view'],
];
