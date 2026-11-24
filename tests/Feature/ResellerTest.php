<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Central\Admin;
use App\Models\Central\Invoice;
use App\Models\Central\Payment;
use App\Models\Central\Seller;
use App\Models\Central\SellerSettlement;
use App\Services\Sellers;
use App\Services\Subscriptions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Resellers and their accounts; who paid and who is due branch by branch; the renewals of every year. */
class ResellerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Admin
    {
        $admin = Admin::create(['name' => 'System Admin', 'email' => 'admin@fida.test', 'password' => 'password123', 'role' => 'owner']);
        $this->central();
        $this->actingAs($admin, 'admin');

        return $admin;
    }

    public function test_a_reseller_sells_takes_the_money_earns_his_commission_and_hands_the_rest_over(): void
    {
        $tenant = $this->clinic();
        $this->admin();

        // a reseller with 10% of what his clients pay
        $this->get('/admin/sellers')->assertOk()->assertSee('New reseller')->assertSee('id="newSeller"', false)->assertSee('data-bs-target="#newSeller"', false);
        $this->post('/admin/sellers', ['name' => 'Ahmad Sales', 'commission_type' => 'percent', 'commission_value' => '150'])->assertSessionHasErrors('commission_value');
        $this->post('/admin/sellers', ['name' => 'Ahmad Sales', 'phone' => '0700111222', 'commission_type' => 'percent', 'commission_value' => '10'])->assertSessionHas('success');
        $ahmad = Seller::sole();
        $this->assertSame('10%', $ahmad->commissionLabel());
        $this->assertFalse($ahmad->canSignIn());
        // a password given on the form of a new reseller lets him sign in at once; it needs his email
        $this->get('/admin/sellers')->assertOk()->assertSee('name="password_confirmation"', false);
        $this->post('/admin/sellers', ['name' => 'Bashir', 'commission_type' => 'flat', 'commission_value' => '5', 'password' => 'bashir-pass1', 'password_confirmation' => 'bashir-pass1'])->assertSessionHasErrors('email');
        $this->post('/admin/sellers', ['name' => 'Bashir', 'email' => 'Bashir@X.test', 'commission_type' => 'flat', 'commission_value' => '5', 'password' => 'bashir-pass1', 'password_confirmation' => 'bashir-pass1'])->assertSessionHas('success');
        $bashir = Seller::where('name', 'Bashir')->sole();
        $this->assertTrue($bashir->canSignIn());
        $this->assertSame('bashir@x.test', $bashir->email);
        $bashir->delete();
        $this->post('/admin/sellers/assign', ['tenant_id' => $tenant->id, 'seller_id' => $ahmad->id])->assertSessionHas('success');
        $this->assertSame($ahmad->id, $tenant->fresh()->seller_id);
        $this->get('/admin/tenants/'.$tenant->id)->assertOk()->assertSee('Ahmad Sales');

        // the first invoice ($500), and the client pays the reseller: he owes us 500 less his 50
        $this->post('/admin/invoices', ['tenant_id' => $tenant->id, 'from_date' => '2026-11-13', 'to_date' => '2027-11-12'])->assertSessionHas('success');
        $invoice = Invoice::sole();
        $this->assertSame('500.00', $invoice->total);
        $this->get('/admin/payments?tenant='.$tenant->id)->assertOk()->assertSee('The money was received by')->assertSee('Ahmad Sales');
        $pay = ['tenant_id' => $tenant->id, 'invoice_id' => $invoice->id, 'method' => 'cash', 'paid_on' => '2026-11-13', 'seller_id' => $ahmad->id];
        $this->post('/admin/payments', ['amount' => '300', 'collected_by_seller' => '1'] + $pay)->assertSessionHas('success');
        $first = Payment::sole();
        $this->assertSame(['30.00', true, $ahmad->id], [$first->commission, $first->collected_by_seller, $first->seller_id]);
        // the rest is paid to us directly: commission still his, but no money in his hands
        $this->post('/admin/payments', ['amount' => '200', 'collected_by_seller' => '0'] + $pay)->assertSessionHas('success');
        $this->assertSame('paid', $invoice->fresh()->status);
        $account = Sellers::account($ahmad);
        $this->assertSame(['500.00', '300.00', '50.00', '250.00', 1], [$account['sales'], $account['collected'], $account['commission'], $account['balance'], $account['clients']]);
        $this->get('/admin/sellers')->assertOk()->assertSee('Ahmad Sales')->assertSee('he owes us')->assertSee('250.00');

        // he hands over 250: settled
        $this->get('/admin/sellers/'.$ahmad->id)->assertOk()->assertSee('Statement of account')->assertSee('Took a payment from a client')->assertSee('He owes us');
        $this->post('/admin/sellers/'.$ahmad->id.'/settlements', ['direction' => 'in', 'amount' => '0', 'method' => 'cash', 'paid_on' => '2026-11-13'])->assertSessionHasErrors('amount');
        $this->post('/admin/sellers/'.$ahmad->id.'/settlements', ['direction' => 'in', 'amount' => '250', 'method' => 'cash', 'paid_on' => '2026-11-13'])->assertSessionHas('success');
        $this->assertSame('0.00', Sellers::account($ahmad)['balance']);
        $this->get('/admin/sellers/'.$ahmad->id)->assertOk()->assertSee('Settled')->assertSee('The reseller paid us');
        $this->assertSame(['270.00', '250.00', '0.00'], Sellers::statement($ahmad)->pluck('balance')->all());

        // a wrong settlement is cancelled and counts no longer; a cancelled payment neither
        $settlement = SellerSettlement::sole();
        $this->delete('/admin/sellers/'.$ahmad->id.'/settlements/'.$settlement->id)->assertSessionHas('success');
        $this->assertSame('250.00', Sellers::account($ahmad)['balance']);
        $this->delete('/admin/payments/'.$first->id)->assertSessionHas('success');
        $this->assertSame(['200.00', '0.00', '20.00', '-20.00'], array_values(array_intersect_key(Sellers::account($ahmad), array_flip(['sales', 'collected', 'commission', 'balance']))));
        // we pay him the 20 we owe
        $this->post('/admin/sellers/'.$ahmad->id.'/settlements', ['direction' => 'out', 'amount' => '20', 'method' => 'bank', 'paid_on' => '2026-11-13'])->assertSessionHas('success');
        $this->assertSame('0.00', Sellers::account($ahmad)['balance']);

        // staff who only look at billing cannot change anything
        $this->actingAs(Admin::create(['name' => 'Sales', 'email' => 'sales@fida.test', 'password' => 'password123', 'role' => 'sales']), 'admin');
        $this->get('/admin/sellers')->assertOk()->assertDontSee('New reseller');
        $this->post('/admin/sellers', ['name' => 'X', 'commission_type' => 'flat', 'commission_value' => '5'])->assertForbidden();
    }

    public function test_a_flat_commission_is_earned_once_for_each_sale_and_a_typed_one_wins(): void
    {
        $tenant = $this->clinic();
        $this->admin();
        $this->post('/admin/sellers', ['name' => 'Bashir Agent', 'commission_type' => 'flat', 'commission_value' => '40'])->assertSessionHas('success');
        $bashir = Seller::sole();
        $this->assertStringContainsString('$40', $bashir->commissionLabel());
        $this->post('/admin/invoices', ['tenant_id' => $tenant->id, 'from_date' => '2026-11-13', 'to_date' => '2027-11-12'])->assertSessionHas('success');
        $pay = ['tenant_id' => $tenant->id, 'invoice_id' => Invoice::sole()->id, 'method' => 'cash', 'paid_on' => '2026-11-13', 'seller_id' => $bashir->id];
        $this->post('/admin/payments', ['amount' => '100'] + $pay)->assertSessionHas('success');
        $this->post('/admin/payments', ['amount' => '100'] + $pay)->assertSessionHas('success');
        $this->assertSame(['40.00', '0.00'], Payment::orderBy('id')->pluck('commission')->all());
        // an agreed exception is typed, and no commission is above the payment
        $this->post('/admin/payments', ['amount' => '100', 'commission' => '500'] + $pay)->assertSessionHasErrors('commission');
        $this->post('/admin/payments', ['amount' => '100', 'commission' => '15'] + $pay)->assertSessionHas('success');
        $this->assertSame('55.00', Sellers::account($bashir)['commission']);
        // a direct sale has no commission
        $this->post('/admin/payments', ['amount' => '50', 'seller_id' => '', 'commission' => '10'] + $pay)->assertSessionHas('success');
        $this->assertSame([null, '0.00'], [Payment::orderByDesc('id')->first()->seller_id, Payment::orderByDesc('id')->first()->commission]);
    }

    public function test_subscriptions_show_which_branch_paid_and_renewals_show_what_comes_every_year(): void
    {
        $tenant = $this->clinic();
        Branch::create(['name' => 'Herat', 'code' => 'HRT', 'type' => Branch::DEPENDENT, 'is_active' => true]);
        $this->admin();

        // nothing invoiced yet
        $sub = Subscriptions::of($tenant);
        $this->assertSame(['none', 'none'], array_column($sub['rows'], 'state'));
        $this->assertSame([2, 0, 2, '150.00'], [$sub['units'], $sub['paid'], $sub['due'], $sub['yearly']]);
        $this->get('/admin/subscriptions')->assertOk()->assertSee('Acme Clinic')->assertSee('Not invoiced')->assertSee('Herat');

        // invoiced (main 500 + branch 100): due until it is paid
        $this->post('/admin/invoices', ['tenant_id' => $tenant->id, 'from_date' => '2026-11-13', 'to_date' => '2027-11-12'])->assertSessionHas('success');
        $invoice = Invoice::sole();
        $this->assertSame('600.00', $invoice->total);
        $sub = Subscriptions::of($tenant);
        $this->assertSame(['due', 'due', '600.00'], [...array_column($sub['rows'], 'state'), $sub['left']]);
        $this->get('/admin/subscriptions?show=paid')->assertOk()->assertDontSee('Acme Clinic');
        $this->get('/admin/subscriptions?show=due')->assertOk()->assertSee('Acme Clinic');
        $this->post('/admin/payments', ['tenant_id' => $tenant->id, 'invoice_id' => $invoice->id, 'amount' => '600', 'method' => 'cash', 'paid_on' => '2026-11-13'])->assertSessionHas('success');
        $sub = Subscriptions::of($tenant);
        $this->assertSame(['paid', 'paid', '2027-11-12', '500.00', '100.00'], [...array_column($sub['rows'], 'state'), $sub['rows'][0]['until'], $sub['rows'][0]['price'], $sub['rows'][1]['price']]);
        $this->get('/admin/subscriptions?show=paid&q=acme')->assertOk()->assertSee('Acme Clinic')->assertSee($invoice->number);

        // a branch opened afterwards is not covered: the clinic paid, that branch is due
        $this->enter($tenant);
        Branch::create(['name' => 'Mazar', 'code' => 'MZR', 'type' => Branch::DEPENDENT, 'is_active' => true]);
        $this->central();
        $sub = Subscriptions::of($tenant);
        $this->assertSame(['paid', 'paid', 'none'], array_column($sub['rows'], 'state'));
        $this->assertSame([3, 2, 1, '200.00'], [$sub['units'], $sub['paid'], $sub['due'], $sub['yearly']]);
        // invoiced alone, in the middle of the year
        $this->post('/admin/invoices', ['tenant_id' => $tenant->id, 'from_date' => '2026-11-13', 'to_date' => '2027-11-12', 'only_new' => '1'])->assertSessionHas('success');
        $this->assertSame(['paid', 'paid', 'due'], array_column(Subscriptions::of($tenant)['rows'], 'state'));

        // the renewals of every year: main 100 + two branches of 50
        $this->get('/admin/renewals?within=all')->assertOk()->assertSee('Acme Clinic')->assertSee('200.00')->assertSee('Record a payment');
        $this->get('/admin/renewals?within=overdue')->assertOk()->assertDontSee('Acme Clinic');
        $tenant->fresh()->update(['licence_expires_on' => '2026-11-01']);
        $this->get('/admin/renewals?within=overdue')->assertOk()->assertSee('Acme Clinic')->assertSee('12 days overdue');
        $this->get('/admin/renewals')->assertOk()->assertSee('Recurring payments');

        // the clinic sees the same, branch by branch, on its subscription page
        \Illuminate\Support\Facades\Auth::guard('admin')->logout();
        $tenant->fresh()->update(['licence_expires_on' => '2027-11-12']);
        $this->enter($tenant->fresh());
        $this->owner();
        $this->get('/service/licence')->assertOk()->assertSee('Subscription of each branch')->assertSee('Mazar')->assertSee('Herat')->assertSee('Due');
    }

    public function test_a_reseller_signs_in_to_his_own_panel_asks_for_clinics_and_reports_the_money_he_took(): void
    {
        $other = $this->clinic(['name' => 'Other Clinic', 'slug' => 'other', 'owner_email' => 'owner@other.test']);
        $tenant = $this->clinic();
        $admin = $this->admin();
        $this->post('/admin/sellers', ['name' => 'Ahmad Sales', 'commission_type' => 'percent', 'commission_value' => '10'])->assertSessionHas('success');
        $ahmad = Seller::sole();
        $this->post('/admin/sellers/assign', ['tenant_id' => $tenant->id, 'seller_id' => $ahmad->id])->assertSessionHas('success');
        $this->post('/admin/invoices', ['tenant_id' => $tenant->id, 'from_date' => '2026-11-13', 'to_date' => '2027-11-12'])->assertSessionHas('success');
        $invoice = Invoice::sole();

        // no sign-in until the administrator gives him an email and a password
        $this->assertFalse($ahmad->canSignIn());
        $this->put('/admin/sellers/'.$ahmad->id.'/login', ['email' => 'Ahmad@Sales.test'])->assertSessionHasErrorsIn('login', 'password');
        $this->put('/admin/sellers/'.$ahmad->id.'/login', ['email' => 'Ahmad@Sales.test', 'password' => 'reseller-pass', 'password_confirmation' => 'reseller-pass'])->assertSessionHas('success');
        $this->assertTrue($ahmad->fresh()->canSignIn());
        $this->get('/admin/sellers/'.$ahmad->id)->assertOk()->assertSee('Can sign in')->assertSee('/reseller/login');
        \Illuminate\Support\Facades\Auth::guard('admin')->logout();

        // his own sign-in page and panel
        $this->get('/reseller')->assertRedirect('/reseller/login');
        $this->get('/reseller/login')->assertOk()->assertSee('Reseller panel');
        $this->post('/reseller/login', ['email' => 'ahmad@sales.test', 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->post('/reseller/login', ['email' => 'ahmad@sales.test', 'password' => 'reseller-pass'])->assertRedirect('/reseller');
        $this->get('/reseller')->assertOk()->assertSee('Welcome, Ahmad Sales')->assertSee('class="app-tabbar"', false);
        $this->get('/admin')->assertRedirect('/admin/login');   // the panel of the administrator stays closed to him
        // he sees his clinic and never another one
        $this->get('/reseller/clinics')->assertOk()->assertSee('Acme Clinic')->assertDontSee('Other Clinic')->assertSee('Due');
        foreach (['activation', 'payments', 'commissions', 'finances', 'account'] as $page) {
            $this->get('/reseller/'.$page)->assertOk()->assertDontSee('Other Clinic');
        }

        // activation: he asks for a new clinic; approved, it is his client
        \Illuminate\Support\Facades\Mail::fake();
        $form = ['name' => 'Kabul Exchange', 'slug' => 'kabul', 'owner_name' => 'Karim Khan', 'owner_email' => 'karim@kabul.test', 'owner_phone' => '0700123456',
            'password' => 'a-good-password', 'password_confirmation' => 'a-good-password', 'branches' => '1'];
        $this->post('/reseller/activation', ['slug' => 'other'] + $form)->assertSessionHasErrors('slug');
        $this->post('/reseller/activation', $form)->assertSessionHas('success');
        $signup = \App\Models\Central\Signup::sole();
        $this->assertSame([$ahmad->id, 'pending'], [$signup->seller_id, $signup->status]);
        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\SignupReceived::class, fn ($mail) => str_contains($mail->render(), 'Ahmad Sales'));
        $this->get('/reseller/activation')->assertOk()->assertSee('Kabul Exchange')->assertSee('Waiting for approval');

        // the money he took: his own clinic only, and it counts when the administrator confirms
        $report = ['tenant_id' => $tenant->id, 'invoice_id' => $invoice->id, 'amount' => '500', 'method' => 'cash', 'paid_on' => '2026-11-13'];
        $this->post('/reseller/payments', ['tenant_id' => $other->id] + $report)->assertSessionHasErrors('tenant_id');
        $this->post('/reseller/payments', $report)->assertSessionHas('success');
        $this->post('/reseller/payments', ['amount' => '70', 'invoice_id' => ''] + $report)->assertSessionHas('success');
        $this->post('/reseller/payments', ['amount' => '5', 'invoice_id' => ''] + $report)->assertSessionHas('success');
        [$claim, $wrong, $mistake] = \App\Models\Central\SellerClaim::orderBy('id')->get()->all();
        $this->assertSame([0, 'unpaid'], [Payment::count(), $invoice->fresh()->status]);
        $this->delete('/reseller/payments/'.$mistake->id)->assertSessionHas('success');   // taken back while it waits
        $this->get('/reseller/payments')->assertOk()->assertSee('Waiting for confirmation');

        $this->actingAs($admin, 'admin');
        $this->get('/admin/sellers')->assertOk()->assertSee('Payments reported by resellers')->assertSee('Ahmad Sales');
        $this->get('/admin/signups')->assertOk()->assertSee('Kabul Exchange')->assertSee('Ahmad Sales');
        $this->post('/admin/signups/'.$signup->id.'/approve', ['slug' => 'kabul'])->assertSessionHas('success');
        $this->assertSame($ahmad->id, \App\Models\Central\Tenant::where('slug', 'kabul')->sole()->seller_id);
        $this->post('/admin/sellers/claims/'.$claim->id.'/confirm')->assertSessionHas('success');
        $payment = Payment::sole();
        $this->assertSame(['500.00', '50.00', true, $ahmad->id, $invoice->id], [$payment->amount, $payment->commission, $payment->collected_by_seller, $payment->seller_id, $payment->invoice_id]);
        $this->assertSame(['paid', 'confirmed', $payment->id], [$invoice->fresh()->status, $claim->fresh()->status, $claim->fresh()->payment_id]);
        $this->post('/admin/sellers/claims/'.$claim->id.'/confirm')->assertSessionHas('danger');   // not twice
        $this->post('/admin/sellers/claims/'.$wrong->id.'/reject', [])->assertSessionHasErrors('reason');
        $this->post('/admin/sellers/claims/'.$wrong->id.'/reject', ['reason' => 'No such payment.'])->assertSessionHas('success');
        $this->assertSame(1, Payment::count());
        \Illuminate\Support\Facades\Auth::guard('admin')->logout();

        // he reads the result: 500 taken, 50 earned, 450 owed to the company
        $this->actingAs($ahmad->fresh(), 'seller');
        $this->get('/reseller/payments')->assertOk()->assertSee('Confirmed')->assertSee('No such payment.')->assertSee($payment->number);
        $this->get('/reseller/commissions')->assertOk()->assertSee('50.00');
        $this->get('/reseller/finances')->assertOk()->assertSee('You owe the company')->assertSee('450.00')->assertSee('You took a payment from a client');
        $this->get('/reseller/clinics')->assertOk()->assertSee('Kabul Exchange')->assertSee('Acme Clinic');
        $this->delete('/reseller/payments/'.$claim->id)->assertNotFound();   // a confirmed report stays

        // his profile and password
        $this->put('/reseller/account', ['name' => 'Ahmad S.', 'current_password' => 'wrong', 'password' => 'new-reseller-pass', 'password_confirmation' => 'new-reseller-pass'])->assertSessionHasErrors('current_password');
        $this->put('/reseller/account', ['name' => 'Ahmad S.', 'phone' => '0799', 'current_password' => 'reseller-pass', 'password' => 'new-reseller-pass', 'password_confirmation' => 'new-reseller-pass'])->assertSessionHas('success');
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('new-reseller-pass', $ahmad->fresh()->password));

        // switched off by the administrator: signed out at his next click
        \Illuminate\Support\Facades\Auth::guard('seller')->logout();
        \Illuminate\Support\Facades\Auth::shouldUse('web');
        $this->post('/reseller/login', ['email' => 'ahmad@sales.test', 'password' => 'new-reseller-pass'])->assertRedirect('/reseller');
        $ahmad->fresh()->update(['is_active' => false]);
        \Illuminate\Support\Facades\Auth::guard('seller')->forgetUser();   // his next request reads him from the database again
        $this->get('/reseller')->assertRedirect('/reseller/login');
        $this->post('/reseller/login', ['email' => 'ahmad@sales.test', 'password' => 'new-reseller-pass'])->assertSessionHasErrors('email');
    }
}
