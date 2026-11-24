<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Central\Admin;
use App\Models\Central\Tenant;
use App\Models\User;
use App\Tenancy\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CentralPanelTest extends TestCase
{
    use RefreshDatabase;

    private function form(array $over = []): array
    {
        return $over + [
            'name' => 'Acme Clinic',
            'slug' => 'acme',
            'max_branches' => 2,
            'owner_name' => 'Owner One',
            'owner_email' => 'owner@acme.test',
            'owner_password' => 'password123',
        ];
    }

    public function test_guests_are_sent_to_the_admin_sign_in_page(): void
    {
        $this->get('/admin/tenants')->assertRedirect(route('admin.login'));
        $this->get('/')->assertOk()->assertSee('Register your clinic');   // the public website
        $this->get('/admin/login')->assertOk();
    }

    public function test_a_system_administrator_can_sign_in(): void
    {
        Admin::create(['name' => 'System Admin', 'email' => 'admin@fida.test', 'password' => 'password123']);

        $this->post('/admin/login', ['email' => 'admin@fida.test', 'password' => 'wrong-password'])->assertSessionHasErrors('email');
        $this->post('/admin/login', ['email' => 'admin@fida.test', 'password' => 'password123'])->assertRedirect(route('admin.home'));
        // on a phone the menu is reached from the bottom bar, as in an app
        $this->get('/admin')->assertOk()->assertSee('Dashboard')->assertSee('class="app-tabbar"', false)->assertSee('id="tabMenu"', false)->assertSee('drawer-profile', false);
        $this->get('/admin/tenants')->assertOk();
    }

    public function test_opening_a_clinic_makes_its_database_main_branch_and_owner(): void
    {
        $this->systemAdmin();

        $this->get('/admin/tenants/create')->assertOk();
        $this->post('/admin/tenants', $this->form())->assertRedirect(route('admin.tenants.index'));

        $tenant = Tenant::where('slug', 'acme')->firstOrFail();
        $this->assertSame('acme.tabib.test', $tenant->domain);
        $this->assertSame('tabib_acme', $tenant->database);
        $this->assertSame(2, $tenant->max_branches);
        $this->assertFileExists(app(Tenancy::class)->sqliteFile('tabib_acme'));
        $this->get('/admin/tenants')->assertOk()->assertSee('Acme Clinic');

        $this->enter($tenant);
        $this->assertSame(1, Branch::where('type', Branch::MAIN)->count());
        $this->assertSame(['owner@acme.test'], User::pluck('email')->all());
        $this->assertSame('Acme Clinic', setting('company_name'));

        $this->post('/login', ['email' => 'owner@acme.test', 'password' => 'password123'])->assertRedirect(route('dashboard'));

        // the dashboard of a new clinic: who is signed in, the subscription, and what to do before the first patient
        $this->get('/')->assertOk()->assertSee('Main branch')->assertSee('Subscription')->assertSee('dash-top', false)->assertSee('Get the clinic ready')
            ->assertSee('Add the doctors of the clinic')->assertSee('1405')->assertSee('data-clock', false)->assertSee('data-primary="tabib"', false)
            ->assertDontSee('quickbar-item', false)->assertDontSee('id="ratesModal"', false)->assertDontSee('/transactions/', false)->assertDontSee('DAB Links')->assertDontSee('Period Closing');
        $this->get('/day')->assertNotFound();
    }

    public function test_the_short_name_is_checked(): void
    {
        $this->systemAdmin();

        $this->post('/admin/tenants', $this->form(['slug' => 'admin']))->assertSessionHasErrors('slug');
        $this->post('/admin/tenants', $this->form(['slug' => 'Has Spaces']))->assertSessionHasErrors('slug');
        $this->assertSame(0, Tenant::count());

        $this->post('/admin/tenants', $this->form())->assertSessionHasNoErrors();
        $this->post('/admin/tenants', $this->form())->assertSessionHasErrors('slug');
        $this->assertSame(1, Tenant::count());
    }

    public function test_a_suspended_clinic_cannot_be_opened(): void
    {
        $this->systemAdmin();
        $this->post('/admin/tenants', $this->form());
        $tenant = Tenant::firstOrFail();

        $this->enter($tenant);
        $this->get('/login')->assertOk();

        $this->central();
        $this->post('/admin/tenants/'.$tenant->id.'/status', ['status' => 'suspended'])->assertRedirect();
        $this->assertSame('suspended', $tenant->fresh()->status);
        $this->get('http://acme.tabib.test/login')->assertStatus(503);

        $this->post('/admin/tenants/'.$tenant->id.'/status', ['status' => 'active']);
        $this->enter($tenant->fresh());
        $this->get('/login')->assertOk();
    }

    public function test_the_licence_of_a_clinic_can_be_changed(): void
    {
        $this->systemAdmin();
        $this->post('/admin/tenants', $this->form());
        $tenant = Tenant::firstOrFail();

        $this->get('/admin/tenants/'.$tenant->id.'/edit')->assertOk();
        $this->put('/admin/tenants/'.$tenant->id, ['name' => 'Acme Family Clinic', 'max_branches' => 5, 'main_branch_price' => '1200', 'branch_price' => '400'])
            ->assertRedirect(route('admin.tenants.index'));

        $tenant->refresh();
        $this->assertSame('Acme Family Clinic', $tenant->name);
        $this->assertSame(5, $tenant->max_branches);
        $this->assertSame('acme.tabib.test', $tenant->domain);
        $this->assertSame('400.00', $tenant->branch_price);
    }

    public function test_an_unknown_address_is_not_found(): void
    {
        $this->get('http://nobody.tabib.test/login')->assertNotFound();
    }

    public function test_the_panel_cannot_be_opened_on_the_address_of_a_clinic(): void
    {
        $this->clinic();

        $this->get('/admin/login')->assertNotFound();
        $this->get('/admin/tenants')->assertNotFound();
    }

    public function test_two_clinics_do_not_see_each_other(): void
    {
        $acme = $this->clinic();
        Branch::create(['name' => 'Herat', 'code' => 'HRT', 'type' => Branch::DEPENDENT]);
        $this->central();

        $beta = $this->clinic(['name' => 'Beta Clinic', 'slug' => 'beta', 'owner_email' => 'owner@beta.test']);
        $this->assertSame(['owner@beta.test'], User::pluck('email')->all());
        $this->assertSame(1, Branch::count());

        $this->enter($acme);
        $this->assertSame(['owner@acme.test'], User::pluck('email')->all());
        $this->assertSame(2, Branch::count());

        // the owner of one clinic cannot sign in to the other
        $this->enter($beta);
        $this->post('/login', ['email' => 'owner@acme.test', 'password' => 'password123'])->assertSessionHasErrors('email');
    }

    public function test_commands_create_an_administrator_and_a_clinic(): void
    {
        $this->artisan('app:create-admin', ['--name' => 'Root', '--email' => 'root@fida.test', '--password' => 'password123'])->assertSuccessful();
        $this->assertSame(1, Admin::where('email', 'root@fida.test')->count());

        $this->artisan('tenants:create', ['slug' => 'gamma', '--name' => 'Gamma Clinic', '--owner-name' => 'Owner', '--owner-email' => 'owner@gamma.test', '--owner-password' => 'password123'])->assertSuccessful();
        $this->assertSame('tabib_gamma', Tenant::where('slug', 'gamma')->value('database'));

        $this->artisan('tenants:migrate')->assertSuccessful();
    }

    public function test_on_cpanel_hosting_the_panel_makes_the_database_and_the_address(): void
    {
        config(['tenancy.cpanel' => ['user' => 'prime', 'token' => 'secret-token', 'url' => 'https://127.0.0.1:2083', 'docroot' => 'public_html'], 'tenancy.base_domain' => 'tabib.af']);
        \Illuminate\Support\Facades\Http::fake([
            '*/execute/Mysql/delete_database' => \Illuminate\Support\Facades\Http::response(['status' => 0, 'errors' => ['The database does not exist.']]),
            '*' => \Illuminate\Support\Facades\Http::response(['status' => 1, 'errors' => null, 'data' => null]),
        ]);
        $cpanel = app(\App\Tenancy\Cpanel::class);
        $this->assertTrue($cpanel->enabled());

        // the database of a clinic, with the rights of the MySQL user of the system on it
        $cpanel->createDatabase('prime_s_demo', 'prime_tabib');
        \Illuminate\Support\Facades\Http::assertSent(fn ($r) => str_ends_with($r->url(), '/execute/Mysql/create_database') && $r['name'] === 'prime_s_demo' && $r->header('Authorization')[0] === 'cpanel prime:secret-token');
        \Illuminate\Support\Facades\Http::assertSent(fn ($r) => str_ends_with($r->url(), '/execute/Mysql/set_privileges_on_database') && $r['user'] === 'prime_tabib' && $r['database'] === 'prime_s_demo');

        // what the panel refuses is said in its own words
        try {
            $cpanel->dropDatabase('prime_s_gone');
            $this->fail('The refusal of the panel was not noticed.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('The database does not exist.', $e->getMessage());
        }

        // opening a clinic makes its address under the base domain; a domain of its own is left to its owner
        $tenant = $this->clinic(['slug' => 'kabul', 'owner_email' => 'owner@kabul.test']);
        $this->assertSame('kabul.tabib.af', $tenant->domain);
        \Illuminate\Support\Facades\Http::assertSent(fn ($r) => str_ends_with($r->url(), '/execute/SubDomain/addsubdomain') && $r['domain'] === 'kabul' && $r['rootdomain'] === 'tabib.af' && $r['dir'] === 'public_html');
        $this->assertFalse($cpanel->addAddress('www.another-clinic.af'));

        // with a wildcard address and certificate no subdomain is made: the new address works at once
        config(['tenancy.cpanel.wildcard' => true]);
        $asked = count(\Illuminate\Support\Facades\Http::recorded());
        $this->assertTrue($cpanel->addAddress('herat.tabib.af'));
        $this->assertCount($asked, \Illuminate\Support\Facades\Http::recorded());

        // without the token nothing is asked of a panel
        config(['tenancy.cpanel.token' => null]);
        $this->assertFalse($cpanel->enabled());
    }

    public function test_a_clinic_registers_on_the_website_waits_on_its_subscription_page_and_is_approved_or_rejected(): void
    {
        config(['tenancy.base_domain' => 'tabib.test']);
        $this->central();

        // the public website: what the system is, the prices and the form; search engines may list it
        $this->get('/')->assertOk()->assertSee('Register your clinic')->assertSee('$500')->assertSee('Already a client?')->assertDontSee('noindex');
        $this->get('/register')->assertOk()->assertSee('Send the request')->assertSee('data-wizard', false)->assertSee('Kind of clinic')->assertSee('Price and send');
        $this->get('/robots.txt')->assertOk()->assertSee('Allow: /');

        $form = ['name' => 'Kabul Family Clinic', 'slug' => 'Kabul', 'licence_no' => 'MOPH-55', 'clinic_type' => 'general', 'province' => 'Kabul', 'city' => 'Kabul', 'doctors' => 3, 'branches' => 2, 'owner_name' => 'Karim Khan',
            'owner_email' => 'karim@kabul.test', 'owner_phone' => '0700123456', 'password' => 'a-good-password', 'password_confirmation' => 'a-good-password', 'message' => 'Call after noon.', 'terms' => '1'];
        // what is refused: a taken or reserved address, a short password, an unknown kind of clinic, the terms not accepted, the box only robots fill
        $this->post('/register', ['clinic_type' => 'casino'] + $form)->assertSessionHasErrors('clinic_type');
        $this->post('/register', ['terms' => null] + $form)->assertSessionHasErrors('terms');
        $this->post('/register', ['slug' => 'admin'] + $form)->assertSessionHasErrors('slug');
        $this->post('/register', ['slug' => 'a b'] + $form)->assertSessionHasErrors('slug');
        $this->post('/register', ['password' => 'short', 'password_confirmation' => 'short'] + $form)->assertSessionHasErrors('password');
        $this->post('/register', ['website' => 'http://spam.test'] + $form)->assertRedirect('/');
        $this->assertSame(0, \App\Models\Central\Signup::count());

        // the request waits and nothing is made for it (no database: spam costs nothing); the administrator is sent the email with its two buttons
        \Illuminate\Support\Facades\Mail::fake();
        \App\Models\Central\CentralSetting::put('signup_email', 'boss@fida.test');
        $response = $this->post('/register', $form)->assertSessionHasNoErrors();
        $signup = \App\Models\Central\Signup::sole();
        $response->assertRedirect('/register/'.$signup->token);
        $this->assertSame(['kabul', 'pending', 2, 3, 'general', 'Kabul'], [$signup->slug, $signup->status, $signup->branches, $signup->doctors, $signup->clinic_type, $signup->city]);
        $this->assertNotSame('a-good-password', $signup->password);
        $this->assertSame([0, null], [\App\Models\Central\Tenant::count(), $signup->tenant_id]);
        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\SignupReceived::class, fn ($mail) => $mail->hasTo('boss@fida.test') && str_contains($mail->approveUrl, '/registration/'.$signup->id.'/approve?')
            && str_contains($mail->rejectUrl, 'signature=') && str_contains($mail->render(), 'Kabul Family Clinic'));
        $this->get('/register/'.$signup->token)->assertOk()->assertSee('We have your request')->assertSee('kabul.tabib.test');
        $this->get('/register/'.str_repeat('a', 32))->assertNotFound();
        // the form asks, as the address is typed, whether it is free
        $this->get('/register')->assertOk()->assertSee('Web address of your clinic (subdomain)')->assertSee('data-check', false);
        $this->getJson('/register-check?address=kabul')->assertOk()->assertExactJson(['address' => 'kabul', 'free' => false]);
        $this->getJson('/register-check?address=Mazar')->assertOk()->assertExactJson(['address' => 'mazar', 'free' => true]);
        $this->getJson('/register-check?address=my sarafi')->assertOk()->assertJson(['free' => false]);
        $this->getJson('/register-check?address=admin')->assertOk()->assertJson(['free' => false]);
        $this->post('/register', ['owner_email' => 'other@kabul.test'] + $form)->assertSessionHasErrors('slug');   // the address is asked for already

        // a clinic can be opened waiting: its owner signs in and sees the system, but every page leads to the subscription page
        $tenant = app(\App\Services\Signups::class)->open($signup);
        $this->assertSame(['pending', $tenant->id, 'kabul.tabib.test'], [$tenant->status, $signup->fresh()->tenant_id, $tenant->domain]);
        $this->get('/register/'.$signup->token)->assertOk()->assertSee('Your clinic is registered');
        $this->enter($tenant);
        $owner = \App\Models\User::where('email', 'karim@kabul.test')->sole();
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('a-good-password', $owner->password));
        $this->actingAs($owner);
        $this->get('/')->assertRedirect('/service/licence');
        $this->get('/branches')->assertRedirect('/service/licence');
        $this->post('/branches', [])->assertRedirect('/service/licence');
        $this->get('/service/licence')->assertOk()->assertSee('Your registration is waiting for approval.')->assertSee('Subscription');
        $this->get('/service/support')->assertOk();
        \Illuminate\Support\Facades\Auth::guard('web')->logout();
        $this->central();

        // the administrator sees it, with a count in the menu
        $this->get('/admin/signups')->assertRedirect('/admin/login');
        $admin = \App\Models\Central\Admin::create(['name' => 'System Admin', 'email' => 'admin@fida.test', 'password' => 'password123', 'role' => 'owner']);
        $this->actingAs($admin, 'admin');
        $this->get('/admin/signups')->assertOk()->assertSee('Kabul Family Clinic')->assertSee('Karim Khan')->assertSee('Call after noon.');
        $this->get('/admin')->assertOk()->assertSee('Registration request')->assertSee('Kabul Family Clinic');   // the bell of the navbar
        $this->get('/admin/settings')->assertOk()->assertSee('Email for new registrations')->assertSee('boss@fida.test');
        // with no address in the settings the owners of the panel are told
        \App\Models\Central\CentralSetting::put('signup_email', '');
        $this->assertSame(['admin@fida.test'], \App\Services\Signups::recipients());

        // a second request is rejected, with the reason the applicant reads; a clinic that waited for it is closed
        $this->post('/register', ['name' => 'Bad Clinic', 'slug' => 'bad', 'owner_email' => 'bad@x.test'] + $form)->assertSessionHasNoErrors();
        $bad = \App\Models\Central\Signup::where('slug', 'bad')->sole();
        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\SignupReceived::class, fn ($mail) => $mail->hasTo('admin@fida.test') && $mail->signup->is($bad));
        $this->assertSame(1, \App\Models\Central\Tenant::count());
        app(\App\Services\Signups::class)->open($bad);
        $this->actingAs($admin, 'admin');
        $this->post('/admin/signups/'.$bad->id.'/reject', [])->assertSessionHasErrors('reason');
        $this->post('/admin/signups/'.$bad->id.'/reject', ['reason' => 'No licence of the health ministry.'])->assertSessionHas('success');
        $this->assertSame(['rejected', null], [$bad->fresh()->status, $bad->fresh()->password]);
        $this->assertSame(['suspended', 'rejected'], [$bad->fresh()->tenant->status, $bad->fresh()->tenant->locked_reason]);
        $this->post('/admin/signups/'.$bad->id.'/approve', [])->assertSessionHas('danger');
        $this->get('http://bad.tabib.test/login')->assertStatus(503)->assertSee('was not approved');

        // approval opens the whole system for the clinic that waited
        $this->post('/admin/signups/'.$signup->id.'/approve', ['slug' => 'kabul', 'licence_expires_on' => '2027-11-13', 'max_branches' => 2])->assertSessionHasNoErrors()->assertSessionHas('success');
        $tenant = $tenant->fresh();
        $this->assertSame(['active', 'kabul.tabib.test', 'Kabul Family Clinic', 'MOPH-55', 2, '0700123456', '2027-11-13'], [$tenant->status, $tenant->domain, $tenant->name, $tenant->licence_no, $tenant->max_branches, $tenant->owner_phone, $tenant->licence_expires_on->toDateString()]);
        $this->assertSame(['approved', $tenant->id, null, $admin->id], [$signup->fresh()->status, $signup->fresh()->tenant_id, $signup->fresh()->password, $signup->fresh()->decided_by]);
        $this->get('/admin/signups?status=approved')->assertOk()->assertSee('kabul.tabib.test');
        $this->get('/admin/signups?status=rejected')->assertOk()->assertSee('No licence of the health ministry.');
        $this->get('/register/'.$signup->token)->assertOk()->assertSee('Your clinic is ready');
        $this->get('/register/'.$bad->token)->assertOk()->assertSee('No licence of the health ministry.');

        // the buttons of the email: a signed address, a page that asks to confirm, and no sign-in needed
        \Illuminate\Support\Facades\Auth::guard('admin')->logout();
        $this->post('/register', ['name' => 'Herat Clinic', 'slug' => 'herat', 'owner_email' => 'h@herat.test'] + $form)->assertSessionHasNoErrors();
        $herat = \App\Models\Central\Signup::where('slug', 'herat')->sole();
        $this->get('/registration/'.$herat->id.'/approve')->assertForbidden();
        $approve = \App\Services\Signups::link($herat, 'approve');
        $this->get($approve)->assertOk()->assertSee('Approve this registration?')->assertSee('Herat Clinic');
        $this->assertSame(['pending', null], [$herat->fresh()->status, $herat->fresh()->tenant_id]);   // opening the link decides nothing
        $this->post($approve)->assertOk()->assertSee('Approved');
        // the clinic is made at this moment, not before
        $this->assertSame(['approved', 'active', 'herat.tabib.test', 2, null], [$herat->fresh()->status, $herat->fresh()->tenant->status, $herat->fresh()->tenant->domain, $herat->fresh()->tenant->max_branches, $herat->fresh()->decided_by]);
        $this->get(\App\Services\Signups::link($herat, 'reject'))->assertOk()->assertSee('This request is already approved');
        $this->post('/register', ['name' => 'Ghazni Clinic', 'slug' => 'ghazni', 'owner_email' => 'g@ghazni.test'] + $form)->assertSessionHasNoErrors();
        $ghazni = \App\Models\Central\Signup::where('slug', 'ghazni')->sole();
        $this->post(\App\Services\Signups::link($ghazni, 'reject'), ['reason' => 'Not a clinic.'])->assertOk()->assertSee('Rejected')->assertSee('Not a clinic.');
        $this->assertSame(['rejected', null, false], [$ghazni->fresh()->status, $ghazni->fresh()->tenant_id, \App\Models\Central\Tenant::where('slug', 'ghazni')->exists()]);

        // spam is deleted: one request with its own button, or the ticked ones; an approved request stays
        $this->post('/register', ['name' => 'Buy Pills', 'slug' => 'pills', 'owner_email' => 'spam@x.test'] + $form)->assertSessionHasNoErrors();
        $spam = \App\Models\Central\Signup::where('slug', 'pills')->sole();
        $this->post('/admin/signups/delete', ['only' => $spam->id])->assertRedirect('/admin/login');
        $this->actingAs($admin, 'admin');
        $this->get('/admin/signups')->assertOk()->assertSee('Buy Pills')->assertSee('Delete the ticked');
        $this->post('/admin/signups/delete', [])->assertSessionHas('danger');
        $this->post('/admin/signups/delete', ['only' => $spam->id])->assertSessionHas('success');
        $this->post('/admin/signups/delete', ['ids' => [$ghazni->id, $bad->id, $herat->id]])->assertSessionHas('success');
        $this->assertSame(['kabul', 'herat'], \App\Models\Central\Signup::orderBy('id')->pluck('slug')->all());
        $this->get('/admin/signups?status=approved')->assertOk()->assertDontSee('Delete the ticked');
        \Illuminate\Support\Facades\Auth::guard('admin')->logout();


        $this->get('/open?address=kabul')->assertRedirect('http://kabul.tabib.test/login');
        $this->get('/open?address=nobody')->assertRedirect('/#signin')->assertSessionHas('danger');

        // the administrator changes the email or the password a person of a clinic signs in with
        $this->actingAs($admin, 'admin');
        $this->get('/admin/tenants/'.$tenant->id)->assertOk()->assertSee('Change email or password')->assertSee('karim@kabul.test');
        $karim = app(\App\Tenancy\Tenancy::class)->run($tenant, fn () => \App\Models\User::where('email', 'karim@kabul.test')->value('id'));
        $this->post('/admin/tenants/'.$tenant->id.'/login', ['user_id' => $karim, 'email' => 'not-an-email'])->assertSessionHasErrorsIn('login', 'email');
        $this->post('/admin/tenants/'.$tenant->id.'/login', ['user_id' => $karim, 'email' => 'karim@kabul.test', 'password' => 'another-password', 'password_confirmation' => 'different'])->assertSessionHasErrorsIn('login', 'password');
        $this->post('/admin/tenants/'.$tenant->id.'/login', ['user_id' => 9999, 'email' => 'x@kabul.test'])->assertSessionHas('danger');
        // the email alone: the password he has stays
        $this->post('/admin/tenants/'.$tenant->id.'/login', ['user_id' => $karim, 'email' => 'Karim.Khan@Kabul.test'])->assertSessionHas('success');
        $this->assertSame('karim.khan@kabul.test', $tenant->fresh()->owner_email);
        $this->post('/admin/tenants/'.$tenant->id.'/login', ['user_id' => $karim, 'email' => 'karim.khan@kabul.test', 'password' => 'another-password', 'password_confirmation' => 'another-password'])->assertSessionHas('success');
        $this->assertTrue(\App\Models\Central\AdminLog::where('action', 'login changed')->where('description', 'like', '%new password%')->exists());
        $this->assertFalse(\App\Models\Central\AdminLog::where('description', 'like', '%another-password%')->exists());
        \Illuminate\Support\Facades\Auth::guard('admin')->logout();
        $this->enter($tenant);
        $changed = \App\Models\User::findOrFail($karim);
        $this->assertSame('karim.khan@kabul.test', $changed->email);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('another-password', $changed->password));
        $this->central();

        // approved: the owner works in the whole system
        $this->enter($tenant);
        $this->actingAs(\App\Models\User::where('email', 'karim.khan@kabul.test')->sole());
        $this->get('/branches')->assertOk();
        $this->get('/service/licence')->assertOk()->assertDontSee('Your registration is waiting for approval.');
    }
}
