<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserAndSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_clinic_has_the_usual_roles(): void
    {
        $this->clinic();

        $roles = Role::orderBy('name')->pluck('name')->all();
        $this->assertSame(['Accountant', 'Administrator', 'Clinic Admin', 'Doctor', 'Nurse', 'Receptionist'], $roles);

        // only a doctor signs prescriptions; the front desk books and takes payments
        $this->assertTrue(Role::findByName('Doctor', 'web')->hasPermissionTo('prescriptions.manage'));
        $this->assertFalse(Role::findByName('Nurse', 'web')->hasPermissionTo('prescriptions.manage'));
        $this->assertTrue(Role::findByName('Receptionist', 'web')->hasPermissionTo('appointments.manage'));
        $this->assertFalse(Role::findByName('Receptionist', 'web')->hasPermissionTo('clinical.view'));
    }

    public function test_the_owner_adds_a_user_with_a_branch_and_a_role(): void
    {
        $this->clinic();
        $this->owner();
        $main = Branch::where('type', Branch::MAIN)->firstOrFail();

        $this->get('/users')->assertOk();
        $this->get('/users/create')->assertOk();
        $this->post('/users', [
            'name' => 'Front Desk', 'email' => 'desk@acme.test', 'phone' => '0700 111 222', 'password' => 'secret-pass-1', 'role' => 'Receptionist',
            'branch_id' => $main->id, 'calendar' => 'shamsi', 'is_active' => '1',
        ])->assertRedirect(route('users.index'));

        $user = User::where('email', 'desk@acme.test')->firstOrFail();
        $this->assertSame($main->id, $user->branch_id);
        $this->assertSame('0700 111 222', $user->phone);
        $this->assertTrue($user->hasRole('Receptionist'));
        $this->assertTrue($user->can('patients.manage'));
        $this->assertFalse($user->can('settings.manage'));
        $this->assertFalse($user->can('branches.view'));
    }

    public function test_a_user_cannot_be_put_in_a_branch_that_is_switched_off(): void
    {
        $this->clinic();
        $this->owner();
        $closed = Branch::create(['name' => 'Old place', 'code' => 'OLD', 'type' => Branch::DEPENDENT, 'is_active' => false]);

        $this->post('/users', [
            'name' => 'Dr. One', 'email' => 'doctor@acme.test', 'password' => 'secret-pass-1', 'role' => 'Doctor',
            'branch_id' => $closed->id, 'calendar' => 'shamsi',
        ])->assertSessionHasErrors('branch_id');
    }

    public function test_role_permission_and_audit_pages_open(): void
    {
        $this->clinic();
        $this->owner();

        $this->get('/roles')->assertOk()->assertSee('Receptionist');
        $this->get('/roles/create')->assertOk();
        $this->get('/permissions')->assertOk();
        $this->get('/audit')->assertOk();

        $this->post('/roles', ['name' => 'Auditor', 'permissions' => ['audit.view', 'branches.view']])->assertRedirect(route('roles.index'));
        $this->assertTrue(Role::findByName('Auditor', 'web')->hasPermissionTo('audit.view'));
    }

    public function test_settings_are_saved_and_logged(): void
    {
        $this->clinic();
        $this->owner();

        $this->get('/settings')->assertOk()->assertSee('Acme Clinic');
        $this->get('/settings')->assertOk()->assertSee('Working hours and appointments');
        $this->put('/settings', [
            'company_name' => 'Acme Family Clinic', 'company_phone' => '0700 000 000', 'licence_no' => 'MoPH-1234',
            'opening_time' => '07:30', 'closing_time' => '18:00', 'slot_minutes' => '20', 'currency' => 'AFN',
        ])->assertRedirect(route('settings.index'));

        $this->assertSame('Acme Family Clinic', setting('company_name'));
        $this->assertSame('MoPH-1234', setting('licence_no'));
        $this->assertSame(['07:30', '18:00', '20'], [setting('opening_time'), setting('closing_time'), setting('slot_minutes')]);

        // the clinic cannot close before it opens, and an appointment has a usual length
        $this->put('/settings', ['company_name' => 'X', 'opening_time' => '18:00', 'closing_time' => '08:00', 'slot_minutes' => '15', 'currency' => 'AFN'])->assertSessionHasErrors('closing_time');
        $this->put('/settings', ['company_name' => 'X', 'opening_time' => '08:00', 'closing_time' => '17:00', 'slot_minutes' => '7', 'currency' => 'AFN'])->assertSessionHasErrors('slot_minutes');
        $this->assertTrue(AuditLog::where('description', 'Settings')->exists());
    }

    public function test_settings_need_the_permission(): void
    {
        $this->clinic();

        $this->userWith(['settings.view']);
        $this->get('/settings')->assertOk();
        $this->put('/settings', ['company_name' => 'X', 'opening_time' => '08:00', 'closing_time' => '17:00', 'slot_minutes' => '15', 'currency' => 'AFN'])->assertForbidden();
    }

    public function test_the_logo_and_the_fonts_of_dari_and_pashto_are_set_in_settings(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        // nothing chosen yet: Noto Kufi Arabic, for Dari and for Pashto
        $this->assertSame('noto-kufi-arabic', \App\Support\Fonts::chosen());
        $this->clinic();
        $this->owner();
        $data = ['company_name' => 'Acme Clinic', 'opening_time' => '08:00', 'closing_time' => '17:00', 'slot_minutes' => '15', 'currency' => 'AFN'];

        $this->get('/company-logo')->assertNotFound();
        $this->put('/settings', $data + ['font_fa' => 'sahel'])->assertSessionHasErrors('font_fa');
        $this->put('/settings', $data + ['font_fa' => 'vazirmatn', 'font_ps' => 'noto-naskh-arabic', 'logo' => \Illuminate\Http\UploadedFile::fake()->create('logo.png', 40, 'image/png')])->assertSessionHasNoErrors();
        $this->assertSame('vazirmatn', setting('font_fa'));
        $this->get('/company-logo')->assertOk();
        // the logo is the icon of the browser tab too, on every page of the clinic
        $this->get('/settings')->assertOk()->assertSee('rel="icon" href="http://acme.tabib.test/company-logo?v=', false)->assertDontSee('img/favicon.svg', false);
        $this->get('/settings')->assertOk()->assertSee('Remove the logo')->assertSee('Noto Kufi Arabic');

        app()->setLocale('ps');
        $this->assertSame('noto-naskh-arabic', \App\Support\Fonts::chosen());
        app()->setLocale('fa');
        $this->assertSame('vazirmatn', \App\Support\Fonts::chosen());
        app()->setLocale('en');

        // the licence of the Ministry of Public Health
        $this->get('/clinic-licence')->assertNotFound();
        $this->put('/settings', $data + ['clinic_licence' => \Illuminate\Http\UploadedFile::fake()->create('licence.pdf', 60, 'application/pdf')])->assertSessionHasNoErrors();
        $this->get('/clinic-licence')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get('/settings')->assertOk()->assertSee('licence.pdf');
        $this->put('/settings', $data + ['remove_clinic_licence' => '1'])->assertSessionHasNoErrors();
        $this->get('/clinic-licence')->assertNotFound();

        $this->put('/settings', $data + ['remove_logo' => '1'])->assertSessionHasNoErrors();
        $this->get('/company-logo')->assertNotFound();
    }

    public function test_a_user_keeps_his_own_profile_password_and_notifications(): void
    {
        $this->clinic();
        $owner = $this->owner();

        $this->get('/')->assertOk()->assertDontSee('DAB Links')->assertSee('Get the clinic ready');
        $this->get('/')->assertOk()->assertSee('My Profile')->assertSee('Change Password')->assertSee('Refresh the System')->assertDontSee('Switch to Gregorian');
        $this->get('/profile')->assertOk()->assertSee($owner->email);
        $this->put('/profile', ['name' => 'Haji Owner', 'email' => 'not-an-email'])->assertSessionHasErrors('email');
        $this->put('/profile', ['name' => 'Haji Owner', 'email' => 'Haji@Acme.test'])->assertSessionHasNoErrors();
        $this->assertSame(['Haji Owner', 'haji@acme.test'], [$owner->fresh()->name, $owner->fresh()->email]);

        $this->get('/password')->assertOk();
        $this->put('/password', ['current_password' => 'wrong', 'password' => 'new-password-1', 'password_confirmation' => 'new-password-1'])->assertSessionHasErrors('current_password');
        $this->put('/password', ['current_password' => 'password123', 'password' => 'new-password-1', 'password_confirmation' => 'different'])->assertSessionHasErrors('password');
        $this->put('/password', ['current_password' => 'password123', 'password' => 'new-password-1', 'password_confirmation' => 'new-password-1'])->assertSessionHasNoErrors();
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('new-password-1', $owner->fresh()->password));

        // notifications: counted on the bell, opened by a click, read one by one or all at once
        \App\Models\Notice::send($owner->id, 'EX-000009 was rejected.', 'The rate is wrong', route('settings.index'));
        \App\Models\Notice::send($owner->id, 'A second message');
        $first = \App\Models\Notice::orderBy('id')->firstOrFail();
        $this->get('/')->assertOk()->assertSee('EX-000009 was rejected.')->assertSee('All notifications');
        $this->get('/messages')->assertOk()->assertSee('A second message');
        $this->assertSame(2, \App\Models\Notice::whereNull('read_at')->count());
        $this->get('/messages/'.$first->id)->assertRedirect(route('settings.index'));
        $this->assertSame(1, \App\Models\Notice::whereNull('read_at')->count());
        $this->post('/messages/read')->assertRedirect();
        $this->assertSame(0, \App\Models\Notice::whereNull('read_at')->count());

        // the calendar is a setting of the clinic now
        $data = ['company_name' => 'Acme Clinic', 'opening_time' => '08:00', 'closing_time' => '17:00', 'slot_minutes' => '15', 'currency' => 'AFN'];
        $this->put('/settings', $data + ['calendar' => 'gregorian'])->assertSessionHasNoErrors();
        $this->assertSame('gregorian', calendar());
        $this->put('/settings', $data + ['calendar' => 'shamsi'])->assertSessionHasNoErrors();
        $this->assertSame('shamsi', calendar());

        $this->post('/refresh')->assertRedirect()->assertSessionHas('success');
        $this->userWith(['patients.view']);
        $this->post('/refresh')->assertForbidden();
        $this->get('/')->assertOk()->assertDontSee('Refresh the System');
    }
}
