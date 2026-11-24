<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Billing: an invoice from the queue, the price list, discounts with a reason, payments, refunds, voiding, receipts and the cash of the day. */
class BillingTest extends TestCase
{
    use RefreshDatabase;

    private function setUpVisit(string $fee = '300'): Appointment
    {
        $this->clinic();
        $this->owner();
        $this->post('/doctors', ['name' => 'Dr. Ahmad', 'email' => 'ahmad@acme.test', 'password' => 'doctor-pass-1', 'fee' => $fee, 'colour' => 'teal', 'is_active' => '1']);
        $this->post('/patients', ['name' => 'Gul Ahmad', 'gender' => 'male', 'age' => '40']);
        $this->post('/appointments', ['walk_in' => '1', 'patient_id' => Patient::sole()->id, 'doctor_id' => \App\Models\Doctor::sole()->id, 'reason' => 'Fever']);

        return Appointment::sole();
    }

    public function test_the_front_desk_bills_a_visit_takes_money_and_gives_it_back(): void
    {
        $visit = $this->setUpVisit();
        $this->post('/price-list', ['name' => 'ECG', 'category' => 'procedure', 'price' => '200', 'is_active' => '1'])->assertRedirect();
        $this->get('/queue')->assertOk()->assertSee('Bill');

        // the invoice starts with the fee of the doctor; the queue then shows it, and it is not made twice
        $this->post('/invoices', ['appointment_id' => $visit->id])->assertRedirect();
        $this->post('/invoices', ['appointment_id' => $visit->id]);
        $invoice = Invoice::sole();
        $this->assertSame(['INV-000001', '300.00', 'unpaid'], [$invoice->number, $invoice->total, $invoice->status]);
        $this->assertStringContainsString('Dr. Ahmad', $invoice->items()->sole()->description);
        $this->get('/queue')->assertSee('Unpaid');

        // a line from the price list, and a discount that needs its reason
        $this->post('/invoices/'.$invoice->id.'/items', ['service_id' => Service::sole()->id, 'quantity' => '1']);
        $this->from('/invoices/'.$invoice->id)->put('/invoices/'.$invoice->id, ['discount' => '100'])->assertSessionHas('danger');
        $this->put('/invoices/'.$invoice->id, ['discount' => '100', 'discount_reason' => 'Poor family']);
        $invoice->refresh();
        $this->assertSame(['500.00', '100.00', '400.00'], [$invoice->subtotal, $invoice->discount, $invoice->total]);

        // money: not more than owed; part of it; then locked
        $this->from('/invoices/'.$invoice->id)->post('/invoices/'.$invoice->id.'/pay', ['amount' => '500', 'method' => 'cash'])->assertSessionHas('danger');
        $this->post('/invoices/'.$invoice->id.'/pay', ['amount' => '300', 'method' => 'cash'])->assertSessionHas('receipt');
        $this->assertSame(['partly', '300.00'], [$invoice->fresh()->status, $invoice->fresh()->paid]);
        $this->from('/invoices/'.$invoice->id)->post('/invoices/'.$invoice->id.'/items', ['description' => 'Dressing', 'unit_price' => '50'])->assertSessionHas('danger');
        $this->post('/invoices/'.$invoice->id.'/pay', ['amount' => '100', 'method' => 'card', 'reference' => 'SLIP-7']);
        $this->assertSame('paid', $invoice->fresh()->status);
        $this->get('/patients/'.$visit->patient_id.'?tab=billing')->assertOk()->assertSee('INV-000001')->assertDontSee('Owes');

        // the receipt and the invoice on paper
        $receipt = Payment::where('number', 'RC-000001')->sole();
        $this->get('/receipts/'.$receipt->id)->assertOk()->assertSee('Receipt RC-000001')->assertSee('300.00');
        $this->get('/invoices/'.$invoice->id.'/print')->assertOk()->assertSee('ECG')->assertSee('400.00');

        // a wrong invoice: give the money back (with a reason), then void it; nothing is deleted
        $this->from('/invoices/'.$invoice->id)->post('/invoices/'.$invoice->id.'/void', ['reason' => 'Wrong patient'])->assertSessionHas('danger');
        $this->from('/invoices/'.$invoice->id)->post('/invoices/'.$invoice->id.'/refund', ['amount' => '500', 'method' => 'cash', 'reason' => 'x'])->assertSessionHas('danger');
        $this->post('/invoices/'.$invoice->id.'/refund', ['amount' => '400', 'method' => 'cash', 'reason' => 'Wrong patient'])->assertSessionHas('success');
        $this->post('/invoices/'.$invoice->id.'/void', ['reason' => 'Wrong patient'])->assertSessionHas('success');
        $this->assertSame(['void', '0.00'], [$invoice->fresh()->status, $invoice->fresh()->paid]);
        $this->assertSame(['RC-000001', 'RC-000002', 'RF-000001'], Payment::orderBy('id')->pluck('number')->all());

        // the cash of the day: 400 taken, 400 given back
        $this->get('/cash?date=2026-11-13')->assertOk()->assertSee('RF-000001')->assertSee('400.00')->assertSee('Card');

        // after a void the visit can be billed again
        $this->post('/invoices', ['appointment_id' => $visit->id]);
        $this->assertSame(2, Invoice::count());
        $this->get('/invoices?status=owed')->assertOk()->assertSee('INV-000002')->assertDontSee('INV-000001');
        $this->get('/patients/'.$visit->patient_id)->assertSee('Owes 300.00');
    }

    public function test_a_free_visit_is_paid_and_a_doctor_only_looks(): void
    {
        $visit = $this->setUpVisit('0');
        $this->post('/invoices', ['appointment_id' => $visit->id]);
        $invoice = Invoice::sole();
        $this->assertSame(['0.00', 'paid'], [$invoice->total, $invoice->status]);

        // the Doctor role sees billing but takes no money
        $this->actingAs(\App\Models\User::where('email', 'ahmad@acme.test')->sole());
        $this->get('/invoices/'.$invoice->id)->assertOk()->assertDontSee('Take the money');
        $this->post('/invoices/'.$invoice->id.'/pay', ['amount' => '10', 'method' => 'cash'])->assertForbidden();
        $this->post('/invoices', ['patient_id' => $visit->patient_id])->assertForbidden();

        // a user without billing sees no invoice at all
        $this->actingAs($this->userWith(['patients.view']));
        $this->get('/invoices')->assertForbidden();
        $this->get('/patients/'.$visit->patient_id.'?tab=billing')->assertOk()->assertDontSee('INV-000001');
    }

    public function test_the_dashboard_shows_the_cash_of_today(): void
    {
        $visit = $this->setUpVisit();
        $this->post('/invoices', ['appointment_id' => $visit->id]);
        $this->post('/invoices/'.Invoice::sole()->id.'/pay', ['amount' => '120', 'method' => 'cash']);
        $this->get('/')->assertOk()->assertSee('Cash taken today')->assertSee('120.00')->assertSee('Unpaid invoices');
    }
}
