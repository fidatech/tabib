<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Central\AdminLog;
use App\Models\Central\Backup;
use App\Models\Central\Tenant;
use App\Models\Central\Ticket;
use App\Models\User;
use App\Services\Billing;
use App\Services\Licence;
use App\Tenancy\Supervisor;
use App\Tenancy\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

/** Everything about one clinic on one page, and the door into its system for the support staff. */
class ClientController extends Controller
{
    /**
     * Change the email or the password one person of a clinic signs in with (he forgot it, or the
     * address was typed wrong at registration). Written in the log of the panel; the password never is.
     */
    public function login(Request $request, Tenancy $tenancy): RedirectResponse
    {
        $tenant = Tenant::findOrFail((int) $request->route('id'));
        $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);
        $data = $request->validateWithBag('login', [
            'user_id' => ['required', 'integer'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['nullable', 'confirmed', \Illuminate\Validation\Rules\Password::min(8)],
        ]);

        try {
            $done = $tenancy->run($tenant, function () use ($data) {
                $user = User::find($data['user_id']);
                if (! $user) {
                    return ['error' => __('This user was not found in the clinic.')];
                }
                if (User::where('email', $data['email'])->where('id', '!=', $user->id)->exists()) {
                    return ['error' => __('Another user of this clinic signs in with this email.')];
                }
                $was = $user->email;
                $first = User::whereHas('roles', fn ($q) => $q->where('name', User::ADMIN_ROLE))->orderBy('id')->value('id');
                $user->forceFill(['email' => $data['email']] + (filled($data['password'] ?? null) ? ['password' => $data['password'], 'remember_token' => null] : []))->save();

                return ['name' => $user->name, 'was' => $was, 'owner' => $first === $user->id];
            });
        } catch (Throwable $e) {
            report($e);
            $done = ['error' => __('The database of this clinic could not be opened.')];
        }
        if (isset($done['error'])) {
            return back()->with('danger', $done['error']);
        }
        // the contact address of the client follows the owner's
        if ($done['owner'] && $done['was'] !== $data['email'] && strtolower((string) $tenant->owner_email) === strtolower($done['was'])) {
            $tenant->update(['owner_email' => $data['email']]);
        }
        $what = array_filter([$done['was'] !== $data['email'] ? 'email '.$done['was'].' → '.$data['email'] : null, filled($data['password'] ?? null) ? 'new password' : null]);
        AdminLog::record('login changed', $tenant, $done['name'].': '.($what ? implode(', ', $what) : 'nothing changed'));

        return back()->with('success', __('The sign-in of :name was changed.', ['name' => $done['name']]));
    }

    public function show(Request $request, Tenancy $tenancy): View
    {
        $tenant = Tenant::findOrFail((int) $request->route('id'));
        try {
            $inside = $tenancy->run($tenant, function () {
                $connection = DB::connection();
                $size = $connection->getDriverName() === 'sqlite'
                    ? (int) @filesize($connection->getDatabaseName())
                    : (int) ($connection->selectOne('select sum(data_length + index_length) as size from information_schema.tables where table_schema = ?', [$connection->getDatabaseName()])->size ?? 0);

                return [
                    'branches' => Branch::orderBy('id')->get(['id', 'name', 'type', 'city', 'province', 'is_active'])
                        ->map(fn (Branch $b) => ['name' => $b->name, 'type' => Branch::TYPES[$b->type] ?? $b->type, 'place' => implode(', ', array_filter([$b->city, $b->province])), 'active' => (bool) $b->is_active,
                            'users' => User::where('branch_id', $b->id)->where('is_active', true)->count()])->all(),
                    'users' => User::where('is_active', true)->count(),
                    // how busy the clinic is: what its people did in the last 30 days (patients and visits come with their modules)
                    'activity' => AuditLog::where('created_at', '>=', now()->subDays(30))->count(),
                    'last' => AuditLog::max('created_at'), 'size' => $size,
                    // the people who sign in: the administrator can change the email or the password of one of them
                    'logins' => User::with('roles:id,name')->orderBy('id')->get(['id', 'name', 'email', 'is_active'])
                        ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'role' => $u->roles->pluck('name')->implode(', '), 'active' => (bool) $u->is_active])->all(),
                ];
            });
        } catch (Throwable $e) {
            report($e);
            $inside = null;   // its database cannot be opened: the page still shows what the panel knows
        }

        return view('central.client', [
            'tenant' => $tenant, 'inside' => $inside, 'licence' => Licence::state($tenant), 'balance' => Billing::balance($tenant),
            'invoices' => $tenant->invoices()->orderByDesc('id')->limit(10)->get(),
            'payments' => $tenant->payments()->orderByDesc('paid_on')->orderByDesc('id')->limit(10)->get(),
            'tickets' => Ticket::where('tenant_id', $tenant->id)->orderByDesc('id')->limit(5)->get(),
            'backups' => Backup::where('tenant_id', $tenant->id)->orderByDesc('id')->limit(5)->get(),
            'logs' => AdminLog::with('admin')->where('tenant_id', $tenant->id)->orderByDesc('id')->limit(10)->get(),
        ]);
    }

    /** The owner to call, and notes of the staff about this clinic. */
    public function note(Request $request): RedirectResponse
    {
        $tenant = Tenant::findOrFail((int) $request->route('id'));
        $tenant->update($request->validate([
            'owner_name' => ['nullable', 'string', 'max:150'], 'owner_phone' => ['nullable', 'string', 'max:40'], 'owner_email' => ['nullable', 'email', 'max:190'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]));
        AdminLog::record('updated', $tenant, 'Contact and notes');

        return back()->with('success', __('Saved.'));
    }

    /**
     * Into the system of a clinic to help it. A reason must be given; it is written in the log of
     * the panel and in the audit log of the clinic, where its owner can read it.
     */
    public function enter(Request $request): RedirectResponse
    {
        $tenant = Tenant::where('status', Tenant::ACTIVE)->findOrFail((int) $request->route('id'));
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:200']]);
        $admin = $request->user('admin');
        AdminLog::record('entered', $tenant, $data['reason']);

        $port = $request->getPort();
        $root = $request->getScheme().'://'.$tenant->domain.(in_array($port, [80, 443, null], true) ? '' : ':'.$port);
        $expires = time() + 60;

        return redirect()->away($root.'/support-enter?'.http_build_query([
            'a' => $admin->id, 'e' => $expires, 'r' => $data['reason'], 's' => Supervisor::seal($tenant->id, $admin->id, $expires, 'support|'.$data['reason']),
        ]));
    }
}
