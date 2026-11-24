<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Central\Admin;
use App\Models\Central\AdminLog;
use App\Models\Central\Announcement;
use App\Models\Central\Backup;
use App\Models\Central\Invoice;
use App\Models\Central\Payment;
use App\Models\Central\Tenant;
use App\Models\Central\Ticket;
use App\Models\Branch;
use App\Services\Billing;
use App\Services\Licence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/** The administration of the service: staff, money, licences, talking to the clinics, backups, health. */
class AdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_page_of_the_panel_opens_and_staff_do_only_what_their_role_allows(): void
    {
        $tenant = $this->clinic();
        $this->central();
        $owner = $this->systemAdmin();

        foreach (['/admin', '/admin/tenants', '/admin/tenants/'.$tenant->id, '/admin/invoices', '/admin/payments', '/admin/income', '/admin/announcements', '/admin/tickets',
            '/admin/backups', '/admin/health', '/admin/logs', '/admin/staff', '/admin/settings'] as $page) {
            $this->get($page)->assertOk();
        }
        $this->get('/admin/inspectors')->assertNotFound();   // the bank inspectors of the old Sarafi system are gone
        $this->get('/admin/tenants/'.$tenant->id)->assertSee('Acme Clinic')->assertSee('Size of the database')->assertSee('Enter its system as support');

        // a member of the support staff
        $this->post('/admin/staff', ['name' => 'Sara Support', 'email' => 'Sara@fida.test', 'role' => 'support', 'password' => 'short'])->assertSessionHasErrors('password');
        $this->post('/admin/staff', ['name' => 'Sara Support', 'email' => 'Sara@fida.test', 'role' => 'support', 'password' => 'a-long-password-1'])->assertSessionHasNoErrors();
        $sara = Admin::where('email', 'sara@fida.test')->firstOrFail();
        // the last owner stays an owner, and active
        $this->from('/admin/staff')->put('/admin/staff/'.$owner->id, ['role' => 'sales'])->assertSessionHas('danger');
        $this->from('/admin/staff')->put('/admin/staff/'.$owner->id, ['is_active' => 0])->assertSessionHas('danger');
        $this->assertSame('owner', $owner->fresh()->role);

        $this->actingAs($sara, 'admin');
        foreach (['/admin', '/admin/tenants', '/admin/tenants/'.$tenant->id, '/admin/tickets', '/admin/announcements', '/admin/backups', '/admin/health'] as $page) {
            $this->get($page)->assertOk();
        }
        foreach (['/admin/staff', '/admin/settings', '/admin/logs', '/admin/invoices', '/admin/payments', '/admin/income', '/admin/tenants/create'] as $page) {
            $this->get($page)->assertForbidden();
        }
        $this->post('/admin/tenants/'.$tenant->id.'/status', ['status' => 'suspended'])->assertForbidden();
        $this->get('/admin')->assertDontSee('Staff and roles')->assertSee('Support tickets');

        // an accountant sees the money and not the support desk
        $sara->update(['role' => 'accountant']);
        $this->get('/admin/payments')->assertOk();
        $this->get('/admin/income')->assertOk();
        $this->get('/admin/tickets')->assertForbidden();
        $this->post('/admin/tenants/'.$tenant->id.'/enter', ['reason' => 'Ticket TKT-00001'])->assertForbidden();
    }

    public function test_payments_settle_an_invoice_move_the_licence_and_can_be_cancelled(): void
    {
        $tenant = $this->clinic();
        $this->central();
        $this->systemAdmin();

        $this->post('/admin/invoices', ['tenant_id' => $tenant->id, 'from_date' => '2026-11-13', 'to_date' => '2027-11-12'])->assertSessionHasNoErrors();
        $invoice = Invoice::firstOrFail();
        $this->assertSame('500.00', $invoice->total);
        $this->get('/admin/invoices/'.$invoice->id)->assertOk()->assertSee('INV-000001')->assertSee('Main branch, first year')->assertSee('Fida Technologies');
        $this->get('/admin/payments?tenant='.$tenant->id.'&invoice='.$invoice->id)->assertOk()->assertSee('500.00');

        // a part of it: still not paid, the licence does not move
        $pay = ['tenant_id' => $tenant->id, 'invoice_id' => $invoice->id, 'method' => 'hesabpay', 'reference' => 'HP-991', 'paid_on' => '2026-11-13'];
        $this->post('/admin/payments', $pay + ['amount' => '0'])->assertSessionHasErrors('amount');
        $this->post('/admin/payments', $pay + ['amount' => '200'])->assertSessionHasNoErrors();
        $this->assertSame('unpaid', $invoice->fresh()->status);
        $this->assertNull(Tenant::findOrFail($tenant->id)->licence_expires_on);
        $this->assertSame(['invoiced' => '500.00', 'paid' => '200.00', 'due' => '300.00'], Billing::balance($tenant));

        // the rest: paid, and the licence runs to the last day of the invoice
        $this->post('/admin/payments', $pay + ['amount' => '300', 'method' => 'cash'])->assertSessionHasNoErrors();
        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame('2027-11-12', Tenant::findOrFail($tenant->id)->licence_expires_on->toDateString());
        $last = Payment::orderByDesc('id')->firstOrFail();
        $this->assertSame('RCP-000002', $last->number);
        $this->get('/admin/payments/'.$last->id.'/receipt')->assertOk()->assertSee('RCP-000002')->assertSee('INV-000001')->assertSee('300.00');

        // a wrong payment is cancelled: it stays on the list, the invoice is open again
        $this->delete('/admin/payments/'.$last->id)->assertRedirect();
        $this->assertTrue($last->fresh()->isCancelled());
        $this->assertSame('unpaid', $invoice->fresh()->status);
        $this->assertSame('300.00', Billing::balance($tenant)['due']);

        // a payment on account, with no invoice
        $this->post('/admin/payments', ['tenant_id' => $tenant->id, 'amount' => '50', 'method' => 'bank', 'paid_on' => '2026-11-13'])->assertSessionHasNoErrors();
        $this->assertSame('250.00', Billing::balance($tenant)['due']);

        $this->get('/admin')->assertOk()->assertSee('250.00');
        $this->get('/admin/income')->assertOk()->assertSee('Acme Clinic')->assertSee('HesabPay');
        $this->get('/admin/tenants/'.$tenant->id)->assertOk()->assertSee('INV-000001');

        // the clinic sees its own invoices and payments inside its system, and nobody else's
        $this->enter($tenant);
        $this->owner();
        $this->get('/service/licence')->assertOk()->assertSee('INV-000001')->assertSee('RCP-000001')->assertDontSee('RCP-000002')->assertSee('250.00');
        $this->get('/service/invoices/'.$invoice->id)->assertOk()->assertSee('INV-000001');
        $this->get('/service/invoices/9999')->assertNotFound();
    }

    public function test_a_licence_is_reminded_then_locked_and_opens_again_when_it_is_renewed(): void
    {
        $tenant = $this->clinic();
        $this->central();
        $this->systemAdmin();
        $this->put('/admin/settings', ['company_name' => 'Fida Technologies', 'company_phone' => '+93 700 000 000', 'remind_days' => 30, 'grace_days' => 7, 'auto_lock' => 1, 'backup_keep_days' => 14])->assertSessionHasNoErrors();
        $this->assertSame(['remind_days' => 30, 'grace_days' => 7, 'auto_lock' => true], Licence::rules());
        // the prices are on the settings page too
        $this->get('/admin/settings')->assertOk()->assertSee('Prices (US dollars)');
        $this->put('/admin/settings', ['company_name' => 'Fida Technologies', 'company_phone' => '+93 700 000 000', 'remind_days' => 30, 'grace_days' => 7, 'auto_lock' => 1, 'backup_keep_days' => 14, 'main_first' => '550', 'main_renewal' => '100', 'branch_first' => '100', 'branch_renewal' => '50'])->assertSessionHasNoErrors();
        $this->assertSame('550.00', Billing::prices()['main_first']);
        $this->get('/admin/invoices')->assertOk()->assertDontSee('Prices (US dollars)')->assertSee('550.00');

        // twenty days left: a reminder on the screens of the clinic
        $tenant->update(['licence_expires_on' => now()->addDays(20)->toDateString()]);
        $this->assertSame('soon', Licence::state($tenant->fresh())['state']);
        $this->enter($tenant->fresh());
        $this->owner();
        $this->get(route('dashboard'))->assertOk()->assertSee('The licence of the system ends in 20 days.');

        // three days after the end: still open, with a warning
        $this->central();
        $tenant->update(['licence_expires_on' => now()->subDays(3)->toDateString()]);
        $this->assertSame(['locked' => 0, 'opened' => 0], Licence::check());
        $this->enter($tenant->fresh());
        $this->get(route('dashboard'))->assertOk()->assertSee('The licence of the system has ended.');

        // past the days of grace: locked by the system
        $this->central();
        $tenant->update(['licence_expires_on' => now()->subDays(10)->toDateString()]);
        $this->artisan('licences:check')->assertSuccessful();
        $this->assertSame(Tenant::SUSPENDED, $tenant->fresh()->status);
        $this->assertSame('licence', $tenant->fresh()->locked_reason);
        $this->get('http://'.$tenant->domain.'/login')->assertStatus(503)->assertSee('The licence of this clinic has ended')->assertSee('+93 700 000 000');

        // an invoice is paid: the licence moves and the clinic opens by itself
        $this->systemAdmin();
        $this->post('/admin/invoices', ['tenant_id' => $tenant->id, 'from_date' => now()->toDateString(), 'to_date' => now()->addYear()->toDateString()])->assertSessionHasNoErrors();
        $invoice = Invoice::firstOrFail();
        $this->post('/admin/payments', ['tenant_id' => $tenant->id, 'invoice_id' => $invoice->id, 'amount' => $invoice->total, 'method' => 'cash', 'paid_on' => now()->toDateString()])->assertSessionHasNoErrors();
        $this->assertSame(Tenant::ACTIVE, $tenant->fresh()->status);
        $this->assertNull($tenant->fresh()->locked_reason);

        // a clinic suspended by hand is not opened by the system
        $this->post('/admin/tenants/'.$tenant->id.'/status', ['status' => 'suspended'])->assertRedirect();
        Licence::check();
        $this->assertSame(Tenant::SUSPENDED, $tenant->fresh()->status);
    }

    public function test_announcements_reach_the_clinic_and_a_ticket_goes_back_and_forth(): void
    {
        $tenant = $this->clinic();
        $this->central();
        $admin = $this->systemAdmin();

        $this->post('/admin/announcements', ['title' => 'New version on Friday', 'body' => 'The system will be updated on Friday night.', 'level' => 'warning'])->assertSessionHasNoErrors();
        $this->post('/admin/announcements', ['title' => 'Old news', 'body' => 'Finished.', 'level' => 'info', 'ends_on' => '2026-01-01'])->assertSessionHasNoErrors();
        $this->get('/admin/announcements')->assertOk()->assertSee('New version on Friday');

        $this->enter($tenant);
        $this->owner();
        $this->get(route('dashboard'))->assertOk()->assertSee('New version on Friday')->assertDontSee('Old news');

        // the clinic asks
        $this->get('/service/support')->assertOk();
        $this->post('/service/support', ['subject' => 'Cannot print a receipt', 'priority' => 'urgent', 'body' => ''])->assertSessionHasErrors('body');
        $this->post('/service/support', ['subject' => 'Cannot print a receipt', 'priority' => 'urgent', 'body' => 'The receipt page stays white.'])->assertSessionHasNoErrors();
        $ticket = Ticket::firstOrFail();
        $this->assertSame(['TKT-00001', 'open', 'urgent', 'Owner One'], [$ticket->number, $ticket->status, $ticket->priority, $ticket->opened_by]);

        // the support desk answers
        $this->central();
        $this->actingAs($admin, 'admin');
        $this->get('/admin')->assertOk()->assertSee('Cannot print a receipt');
        $this->get('/admin/tickets?status=open')->assertOk()->assertSee('TKT-00001');
        $this->get('/admin/tickets/'.$ticket->id)->assertOk()->assertSee('The receipt page stays white.');
        $this->put('/admin/tickets/'.$ticket->id, ['body' => 'Please refresh the system from your profile menu.'])->assertSessionHasNoErrors();
        $this->assertSame('answered', $ticket->fresh()->status);

        // the clinic reads the answer, writes once more, then closes it
        $this->enter($tenant);
        $this->owner();
        $this->get('/service/support/'.$ticket->id)->assertOk()->assertSee('Please refresh the system from your profile menu.');
        $this->post('/service/support/'.$ticket->id, ['body' => 'It works now, thank you.'])->assertSessionHasNoErrors();
        $this->assertSame('open', $ticket->fresh()->status);
        $this->post('/service/support/'.$ticket->id, ['close' => 1])->assertSessionHasNoErrors();
        $this->assertSame('closed', $ticket->fresh()->status);
        $this->assertSame(3, $ticket->messages()->count());

        // stopped announcements are gone
        $this->central();
        $this->actingAs($admin, 'admin');
        $shown = Announcement::where('title', 'New version on Friday')->firstOrFail();
        $this->put('/admin/announcements/'.$shown->id)->assertSessionHas('success');
        $this->assertFalse($shown->fresh()->is_active);
        $this->enter($tenant);
        $this->owner();
        $this->get(route('dashboard'))->assertOk()->assertDontSee('The system will be updated on Friday night.');
    }

    public function test_a_backup_is_made_downloaded_and_put_back(): void
    {
        $tenant = $this->clinic();
        Branch::create(['code' => 'B-1', 'name' => 'Before', 'type' => Branch::DEPENDENT]);
        $this->central();
        $this->systemAdmin();

        $this->post('/admin/backups', ['tenant_id' => $tenant->id])->assertSessionHasNoErrors();
        $backup = Backup::firstOrFail();
        $this->assertSame('manual', $backup->kind);
        $this->assertFileExists($backup->path());
        $this->assertGreaterThan(0, $backup->size);
        $this->get('/admin/backups')->assertOk()->assertSee('Acme Clinic')->assertSee('Put back');
        $this->get('/admin/backups/'.$backup->id.'/download')->assertOk();

        // something is recorded after the backup
        $this->enter($tenant);
        Branch::create(['code' => 'B-2', 'name' => 'After', 'type' => Branch::DEPENDENT]);
        $this->assertSame(2, Branch::where('type', Branch::DEPENDENT)->count());

        // the short name must be typed
        $this->central();
        $this->systemAdmin();
        $this->from('/admin/backups')->post('/admin/backups/'.$backup->id.'/restore', ['confirm' => 'wrong'])->assertSessionHas('danger');
        $this->post('/admin/backups/'.$backup->id.'/restore', ['confirm' => 'acme'])->assertSessionHasNoErrors()->assertSessionMissing('danger');
        $this->assertSame(2, Backup::count());   // what it had was saved first
        $this->enter($tenant);
        $this->assertSame(['Before'], Branch::where('type', Branch::DEPENDENT)->orderBy('id')->pluck('name')->all());

        // the nightly command, and the old copies it removes
        $this->central();
        $this->artisan('tenants:backup')->assertSuccessful();
        $nightly = Backup::where('kind', 'auto')->firstOrFail();
        $nightly->forceFill(['created_at' => now()->subDays(40)])->save();
        $this->artisan('tenants:backup')->assertSuccessful();
        $this->assertNull(Backup::find($nightly->id));
        $this->assertFileDoesNotExist($nightly->path());

        foreach (Backup::all() as $left) {
            \App\Services\Backups::remove($left);
        }
    }

    public function test_the_support_staff_enter_a_clinic_with_a_reason_that_its_owner_can_read(): void
    {
        $tenant = $this->clinic();
        $this->central();
        $admin = $this->systemAdmin();

        $this->post('/admin/tenants/'.$tenant->id.'/enter', ['reason' => ''])->assertSessionHasErrors('reason');
        $link = $this->post('/admin/tenants/'.$tenant->id.'/enter', ['reason' => 'Ticket TKT-00001'])->assertRedirect()->headers->get('Location');
        $this->assertStringContainsString('acme.tabib.test/support-enter?', $link);
        $this->assertTrue(AdminLog::where('action', 'entered')->where('description', 'Ticket TKT-00001')->exists());
        parse_str((string) parse_url($link, PHP_URL_QUERY), $ticket);

        $this->enter($tenant);
        $this->get('/support-enter?'.http_build_query(['r' => 'Another reason'] + $ticket))->assertForbidden();
        $this->get('/support-enter?'.http_build_query($ticket))->assertRedirect(route('dashboard'));
        $this->assertSame('support@fida.af', Auth::user()->email);
        $this->assertTrue(AuditLog::where('description', 'Fida support: '.$admin->name.' — Ticket TKT-00001')->exists());
        $this->get(route('dashboard'))->assertOk();

        // the contact and the notes of the staff
        $this->central();
        $this->actingAs($admin, 'admin');
        $this->put('/admin/tenants/'.$tenant->id.'/note', ['owner_name' => 'Haji Owner', 'owner_phone' => '0700111222', 'notes' => 'Pays in cash.'])->assertSessionHasNoErrors();
        $this->get('/admin/tenants/'.$tenant->id)->assertOk()->assertSee('Haji Owner')->assertSee('Pays in cash.');
        $this->post('/admin/health/clear')->assertRedirect();
        $this->post('/admin/health/migrate')->assertRedirect();
        $this->get('/admin/health')->assertOk()->assertSee('Up to date');
        $this->get('/admin/logs?q=entered')->assertOk()->assertSee('Ticket TKT-00001');
    }
}
