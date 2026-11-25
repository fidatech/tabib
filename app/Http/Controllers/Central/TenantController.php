<?php

namespace App\Http\Controllers\Central;

use App\Exceptions\UserError;
use App\Http\Controllers\Controller;
use App\Models\Central\AdminLog;
use App\Models\Central\Tenant;
use App\Tenancy\TenantManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Throwable;

/** The list of clinics: open a new one, change its licence, suspend it. */
class TenantController extends Controller
{
    public function index(Request $request): View
    {
        $term = trim((string) $request->query('q', ''));

        $tenants = Tenant::query()
            ->when($term !== '', fn ($q) => $q->where(fn ($i) => $i->where('name', 'like', "%$term%")->orWhere('slug', 'like', "%$term%")->orWhere('domain', 'like', "%$term%")))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('central.tenants.index', ['tenants' => $tenants, 'term' => $term]);
    }

    public function create(): View
    {
        return view('central.tenants.form', ['tenant' => null]);
    }

    public function store(Request $request, TenantManager $manager): RedirectResponse
    {
        $data = $request->validate($this->rules(null) + [
            'slug' => ['required', 'string', 'regex:/^[a-z][a-z0-9-]{2,39}$/', Rule::notIn(config('tenancy.reserved_slugs')), Rule::unique(Tenant::class, 'slug')],
            'owner_name' => ['required', 'string', 'max:255'],
            'owner_email' => ['required', 'email', 'max:255'],
            'owner_password' => ['required', Password::min(8)],
        ]);

        $domain = strtolower($data['domain'] ?? '') ?: $data['slug'].'.'.config('tenancy.base_domain');
        if (Tenant::where('domain', $domain)->exists()) {
            throw new UserError(__('The address :domain is already used by another clinic.', ['domain' => $domain]));
        }

        try {
            $tenant = $manager->create($data);
        } catch (Throwable $e) {
            report($e);

            throw new UserError(__('The clinic could not be created: :reason', ['reason' => $e->getMessage()]));
        }

        AdminLog::record('created', $tenant);

        return redirect()->route('admin.tenants.index')->with('success', __(':name is ready at :domain.', ['name' => $tenant->name, 'domain' => $tenant->domain]));
    }

    public function edit(Request $request): View
    {
        return view('central.tenants.form', ['tenant' => Tenant::findOrFail($request->route('id'))]);
    }

    public function update(Request $request): RedirectResponse
    {
        $tenant = Tenant::findOrFail($request->route('id'));
        $data = $request->validate($this->rules($tenant));

        $data['domain'] = strtolower($data['domain'] ?? '') ?: $tenant->domain;
        $tenant->update($data);
        AdminLog::record('updated', $tenant);

        return redirect()->route('admin.tenants.index')->with('success', __(':name saved.', ['name' => $tenant->name]));
    }

    /** Suspend, reactivate or archive. A clinic that is not active cannot be opened by its users. */
    public function status(Request $request): RedirectResponse
    {
        $tenant = Tenant::findOrFail($request->route('id'));
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(Tenant::STATUSES))]]);

        $tenant->update(['status' => $data['status']]);
        // activating a clinic that waited for approval is the approval of its request
        if ($data['status'] === Tenant::ACTIVE) {
            \App\Models\Central\Signup::where('tenant_id', $tenant->id)->where('status', 'pending')
                ->update(['status' => 'approved', 'decided_by' => $request->user('admin')?->id, 'decided_at' => now(), 'password' => null]);
        }
        AdminLog::record($data['status'], $tenant);

        return back()->with('success', __(':name is now :status.', ['name' => $tenant->name, 'status' => __(Tenant::STATUSES[$data['status']])]));
    }

    private function rules(?Tenant $tenant): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'name_local' => ['nullable', 'string', 'max:150'],
            'domain' => ['nullable', 'string', 'max:190', 'regex:/^[a-z0-9]([a-z0-9.-]*[a-z0-9])?$/i', Rule::notIn(config('tenancy.central_domains')), Rule::unique(Tenant::class, 'domain')->ignore($tenant?->id)],
            'licence_no' => ['nullable', 'string', 'max:60'],
            'main_branch_price' => ['nullable', 'numeric', 'min:0'],
            'branch_price' => ['nullable', 'numeric', 'min:0'],
            'max_branches' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'max_doctors' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'max_sms_month' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'licence_expires_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
