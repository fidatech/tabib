<?php

namespace App\Services;

use App\Models\Central\AdminLog;
use App\Models\Central\CentralSetting;
use App\Models\Central\Tenant;

/**
 * The licence of a clinic runs to a date. Some days before it the clinic is reminded on its own
 * screens; after it there are days of grace; then, if the administrator wants it so, the system
 * locks the clinic by itself, and opens it again the moment the licence is extended.
 */
class Licence
{
    public const DEFAULTS = ['remind_days' => '30', 'grace_days' => '7', 'auto_lock' => '1'];

    /** @return array{remind_days: int, grace_days: int, auto_lock: bool} */
    public static function rules(): array
    {
        $read = fn (string $key) => CentralSetting::read($key) ?? self::DEFAULTS[$key];

        return ['remind_days' => max(0, (int) $read('remind_days')), 'grace_days' => max(0, (int) $read('grace_days')), 'auto_lock' => $read('auto_lock') === '1'];
    }

    /**
     * Where a clinic stands today: "ok", "soon" (inside the reminder days), "grace" (ended, still
     * open) or "ended" (past the days of grace). Without a date there is nothing to end.
     *
     * @return array{state: string, days: int|null, lock_in: int|null}
     */
    public static function state(Tenant $tenant, ?string $today = null): array
    {
        $rules = self::rules();
        $days = $tenant->daysLeft($today);
        if ($days === null) {
            return ['state' => 'ok', 'days' => null, 'lock_in' => null];
        }
        $state = $days > $rules['remind_days'] ? 'ok' : ($days >= 0 ? 'soon' : ($days >= -$rules['grace_days'] ? 'grace' : 'ended'));

        return ['state' => $state, 'days' => $days, 'lock_in' => $days < 0 ? max(0, $rules['grace_days'] + $days + 1) : null];
    }

    /**
     * Lock what has run out, open what was paid. Only a clinic the system locked itself is opened
     * again by it: one the administrator suspended by hand stays suspended.
     *
     * @return array{locked: int, opened: int}
     */
    public static function check(?Tenant $only = null, ?string $today = null): array
    {
        $rules = self::rules();
        $result = ['locked' => 0, 'opened' => 0];
        foreach ($only ? [$only->fresh()] : Tenant::whereIn('status', [Tenant::ACTIVE, Tenant::SUSPENDED])->get() as $tenant) {
            $state = self::state($tenant, $today)['state'];
            if ($tenant->status === Tenant::ACTIVE && $state === 'ended' && $rules['auto_lock']) {
                $tenant->update(['status' => Tenant::SUSPENDED, 'locked_reason' => 'licence']);
                AdminLog::record('locked', $tenant, 'Licence ended on '.$tenant->licence_expires_on->toDateString());
                $result['locked']++;
            } elseif ($tenant->status === Tenant::SUSPENDED && $tenant->locked_reason === 'licence' && ($state !== 'ended' || ! $rules['auto_lock'])) {
                $tenant->update(['status' => Tenant::ACTIVE, 'locked_reason' => null]);
                AdminLog::record('opened', $tenant, 'Licence runs to '.$tenant->licence_expires_on?->toDateString());
                $result['opened']++;
            }
        }

        return $result;
    }
}
