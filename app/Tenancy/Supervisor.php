<?php

namespace App\Tenancy;

use App\Models\Branch;
use App\Models\User;

/**
 * The account the support staff of Fida Technologies work as inside a clinic. They come in from
 * the administration panel with a ticket for one minute and the reason they gave; nobody can sign
 * in to this account with a password, and every page it opens is written in the audit log.
 */
class Supervisor
{
    public const SUPPORT_EMAIL = 'support@fida.af';

    /** @param  string  $what  what the ticket is for, with the reason the support staff gave */
    public static function seal(int $tenantId, int $personId, int $expires, string $what): string
    {
        return hash_hmac('sha256', $what.'|'.$tenantId.'|'.$personId.'|'.$expires, (string) config('app.key'));
    }

    /**
     * The account of the support staff in the clinic that is open. It has the rights of an
     * administrator of the clinic, and every entry is written in the audit log.
     */
    public static function supportUser(): User
    {
        $user = User::firstOrNew(['email' => self::SUPPORT_EMAIL]);
        if (! $user->exists) {
            $user->password = \Illuminate\Support\Str::random(48);
        }
        $user->forceFill(['name' => 'Fida Support', 'is_active' => true, 'branch_id' => Branch::main()->id])->save();
        $user->syncRoles([User::ADMIN_ROLE]);

        return $user;
    }
}
