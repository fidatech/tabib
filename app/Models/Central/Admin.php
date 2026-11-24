<?php

namespace App\Models\Central;

use Illuminate\Foundation\Auth\User as Authenticatable;

/** A system administrator of Fida Technologies: signs in to the central panel, not to a clinic. */
class Admin extends Authenticatable
{
    use UsesCentralConnection;

    protected $fillable = ['name', 'email', 'password', 'is_active', 'role'];

    /** The staff of Fida Technologies and what each kind of them may do in the panel. */
    public const ROLES = ['owner' => 'Owner', 'sales' => 'Sales', 'accountant' => 'Accountant', 'support' => 'Support'];

    public const ABILITIES = [
        'owner' => ['*'],
        'sales' => ['dashboard', 'clients.view', 'clients.manage', 'billing.view', 'announcements'],
        'accountant' => ['dashboard', 'clients.view', 'billing.view', 'billing.manage', 'reports'],
        'support' => ['dashboard', 'clients.view', 'tickets', 'announcements', 'enter', 'backups', 'health'],
    ];

    /** May this member of staff do that? The owner may do everything. */
    public function allows(string $ability): bool
    {
        $mine = self::ABILITIES[$this->role ?: 'owner'] ?? [];

        return in_array('*', $mine, true) || in_array($ability, $mine, true);
    }

    protected $hidden = ['password', 'remember_token'];

    public function initials(): string
    {
        $words = preg_split('/\s+/u', trim($this->name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $letters = array_map(fn (string $word) => mb_substr($word, 0, 1), array_slice($words, 0, 2));

        return mb_strtoupper(implode('', $letters)) ?: '?';
    }

    protected function casts(): array
    {
        return ['password' => 'hashed', 'is_active' => 'boolean'];
    }
}
