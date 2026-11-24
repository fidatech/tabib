<?php

namespace App\Services;

use App\Exceptions\UserError;
use App\Mail\SignupReceived;
use App\Models\Central\Admin;
use App\Models\Central\AdminLog;
use App\Models\Central\CentralSetting;
use App\Models\Central\Signup;
use App\Models\Central\Tenant;
use App\Tenancy\TenantManager;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Throwable;

/**
 * The life of a registration made on the website. A request only waits: no database and no address
 * are made for it until the administrator approves, in the panel or with the two buttons of the
 * email he is sent. Requests that are not wanted (spam) are deleted.
 */
class Signups
{
    public function __construct(private TenantManager $manager) {}

    /**
     * Open the clinic of a request before it is approved: its owner signs in but only the subscription
     * page opens (Tenant::PENDING). Registration does not do this by itself, so spam makes no databases.
     */
    public function open(Signup $signup): ?Tenant
    {
        try {
            $tenant = $this->create($signup, $signup->slug, Tenant::PENDING);
        } catch (Throwable $e) {
            report($e);

            return null;
        }
        $signup->update(['tenant_id' => $tenant->id]);

        return $tenant;
    }

    /** @param array{slug?: string, licence_expires_on?: ?string, max_branches?: ?int} $with */
    public function approve(Signup $signup, array $with = [], ?Admin $by = null): Tenant
    {
        if (! $signup->isPending()) {
            throw new UserError(__('This request is already decided.'));
        }
        $tenant = $signup->tenant;
        if (! $tenant) {
            // a request of before, or one whose clinic could not be made on the day: it is made now
            $slug = $with['slug'] ?? $signup->slug;
            if (Tenant::where('domain', $slug.'.'.config('tenancy.base_domain'))->orWhere('slug', $slug)->exists()) {
                throw new UserError(__('The address :domain is already used by another clinic.', ['domain' => $slug.'.'.config('tenancy.base_domain')]));
            }
            try {
                $tenant = $this->create($signup, $slug, Tenant::ACTIVE);
            } catch (Throwable $e) {
                report($e);

                throw new UserError(__('The clinic could not be created: :reason', ['reason' => $e->getMessage()]));
            }
        }
        $tenant->forceFill(array_filter(['licence_expires_on' => $with['licence_expires_on'] ?? null, 'max_branches' => $with['max_branches'] ?? null], fn ($v) => $v !== null)
            + ['status' => Tenant::ACTIVE, 'locked_reason' => null])->save();
        if ($signup->seller_id && ! $tenant->seller_id) {
            $tenant->forceFill(['seller_id' => $signup->seller_id])->save();   // asked for by a reseller: it is his client
        }
        $signup->update(['status' => 'approved', 'slug' => $tenant->slug, 'tenant_id' => $tenant->id, 'decided_by' => $by?->id, 'decided_at' => now(), 'password' => null]);
        AdminLog::record('created', $tenant, 'Approved the request of the website'.($by ? '' : ' (from the email)'));

        return $tenant;
    }

    public function reject(Signup $signup, string $reason, ?Admin $by = null): void
    {
        if (! $signup->isPending()) {
            throw new UserError(__('This request is already decided.'));
        }
        // the clinic that was waiting is closed; its data stays until the administrator removes it
        $signup->tenant?->forceFill(['status' => Tenant::SUSPENDED, 'locked_reason' => 'rejected'])->save();
        $signup->update(['status' => 'rejected', 'reason' => $reason, 'decided_by' => $by?->id, 'decided_at' => now(), 'password' => null]);
        AdminLog::record('rejected', $signup->tenant, 'Request of '.$signup->name.($by ? '' : ' (from the email)'));
    }

    /** Delete requests that were never approved. A clinic that waited for one of them is closed. Returns how many went. */
    public function delete(array $ids): int
    {
        $gone = 0;
        foreach (Signup::with('tenant')->whereIn('id', $ids)->where('status', '!=', 'approved')->get() as $signup) {
            if ($signup->tenant?->isPending()) {
                $signup->tenant->forceFill(['status' => Tenant::SUSPENDED, 'locked_reason' => 'rejected'])->save();
            }
            $signup->delete();
            $gone++;
        }
        if ($gone) {
            AdminLog::record('deleted', null, $gone.' registration request(s)');
        }

        return $gone;
    }

    /** Who is told about a new request: the address of the settings, or else the owners of the panel. */
    public static function recipients(): array
    {
        $set = array_filter(array_map('trim', preg_split('/[,;\s]+/', (string) CentralSetting::read('signup_email')) ?: []), fn ($a) => filter_var($a, FILTER_VALIDATE_EMAIL));

        return array_values(array_unique($set ?: Admin::where('role', 'owner')->pluck('email')->filter()->all()));
    }

    /** The email with the request and its two buttons. A mail that fails never stops a registration. */
    public function notify(Signup $signup): bool
    {
        $to = self::recipients();
        if (! $to) {
            return false;
        }
        try {
            Mail::to($to)->send(new SignupReceived($signup, self::link($signup, 'approve'), self::link($signup, 'reject')));

            return true;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }

    /** A signed address, good for 30 days, that opens the page where the decision is confirmed. */
    public static function link(Signup $signup, string $action): string
    {
        return URL::temporarySignedRoute('site.decide', now()->addDays(30), ['id' => $signup->id, 'action' => $action]);
    }

    private function create(Signup $signup, string $slug, string $status): Tenant
    {
        $tenant = $this->manager->create([
            'name' => $signup->name, 'name_local' => $signup->name_local, 'slug' => $slug, 'licence_no' => $signup->licence_no,
            'owner_name' => $signup->owner_name, 'owner_email' => $signup->owner_email, 'owner_password' => $signup->password,   // already hashed
            'owner_phone' => $signup->owner_phone, 'province' => $signup->province, 'main_branch_city' => $signup->city,
            'notes' => trim(__('Registered on the website.').' '.$signup->owner_phone.' '.$signup->message),
        ]);
        $tenant->forceFill(['status' => $status, 'owner_name' => $signup->owner_name, 'owner_phone' => $signup->owner_phone, 'owner_email' => $signup->owner_email])->save();

        return $tenant;
    }
}
