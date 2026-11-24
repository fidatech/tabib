<?php

namespace Tests;

use App\Models\Central\Admin;
use App\Models\Central\Tenant;
use App\Models\User;
use App\Support\Permissions;
use App\Tenancy\Tenancy;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\Models\Role;

abstract class TestCase extends BaseTestCase
{
    protected ?Tenant $tenant = null;

    protected function setUp(): void
    {
        parent::setUp();

        // a fixed working day, so tests do not depend on the clock
        $this->travelTo(Carbon::parse('2026-11-13 10:00:00', 'Asia/Kabul'));

        // every test gets its own, empty folder of clinic databases
        config(['tenancy.sqlite_path' => storage_path('framework/testing/tenants')]);
        $this->removeTenantDatabases();
    }

    protected function tearDown(): void
    {
        if ($this->app) {
            app(Tenancy::class)->end();
            $this->removeTenantDatabases();
        }

        parent::tearDown();
    }

    /** Open a new clinic and step into it: requests now go to its address. */
    protected function clinic(array $attributes = []): Tenant
    {
        $tenant = app(TenantManager::class)->create($attributes + [
            'name' => 'Acme Clinic',
            'slug' => 'acme',
            'owner_name' => 'Owner One',
            'owner_email' => 'owner@acme.test',
            'owner_password' => 'password123',
        ]);

        return $this->enter($tenant);
    }

    protected function enter(Tenant $tenant): Tenant
    {
        app(Tenancy::class)->initialize($tenant);
        URL::forceRootUrl('http://'.$tenant->domain);
        auth()->shouldUse('web');

        return $this->tenant = $tenant;
    }

    /** Back to the address of the system administrator panel. */
    protected function central(): void
    {
        app(Tenancy::class)->end();
        URL::forceRootUrl('http://localhost');
    }

    /** The signed-in owner of the clinic we are in. */
    protected function owner(): User
    {
        $user = User::whereHas('roles', fn ($q) => $q->where('name', User::ADMIN_ROLE))->orderBy('id')->firstOrFail();
        $this->actingAs($user);

        return $user;
    }

    /** A signed-in user of the clinic we are in, whose role has only the given permissions. */
    protected function userWith(array $permissions): User
    {
        Permissions::sync();
        $role = Role::findOrCreate('Test role '.md5(implode(',', $permissions)), 'web');
        $role->syncPermissions($permissions);
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    /** A signed-in system administrator of the central panel. */
    protected function systemAdmin(): Admin
    {
        $admin = Admin::firstOrCreate(['email' => 'admin@fida.test'], ['name' => 'System Admin', 'password' => 'password123']);
        $this->actingAs($admin, 'admin');

        return $admin;
    }

    private function removeTenantDatabases(): void
    {
        foreach (glob(storage_path('framework/testing/tenants').'/*.sqlite') ?: [] as $file) {
            @unlink($file);
        }
    }
}
