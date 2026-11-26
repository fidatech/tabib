<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The share of the doctors, the expenses of the clinic, and its profit. (The clock stands at 2026-11-13.) */
class CommissionAndExpensesTest extends TestCase
{
    use RefreshDatabase;

    /** Two patients seen by a doctor: 300 paid for one, 300 paid then 100 given back for the other. */
    private function month(array $doctor): Doctor
    {
        $this->clinic();
        $this->owner();
        $this->post('/doctors', $doctor + ['name' => 'Dr. Ahmad', 'email' => 'ahmad@acme.test', 'password' => 'doctor-pass-1', 'fee' => '300', 'colour' => 'teal', 'is_active' => '1']);
        $d = Doctor::sole();
        foreach (['Gul Ahmad', 'Mariam'] as $i => $name) {
            $this->post('/patients', ['name' => $name, 'gender' => $i ? 'female' : 'male', 'age' => '40']);
            $this->post('/appointments', ['walk_in' => '1', 'patient_id' => Patient::where('name', $name)->sole()->id, 'doctor_id' => $d->id]);
            $a = Appointment::latest('id')->first();
            $this->post('/appointments/'.$a->id.'/start');
            $this->post('/appointments/'.$a->id.'/complete');
            $this->post('/invoices', ['appointment_id' => $a->id]);
            $inv = Invoice::latest('id')->first();
            $this->post('/invoices/'.$inv->id.'/pay', ['amount' => '300', 'method' => 'cash']);
        }
        $this->post('/invoices/'.$inv->id.'/refund', ['amount' => '100', 'method' => 'cash', 'reason' => 'Test not done']);

        return $d;
    }

    public function test_a_percent_of_the_money_taken_is_due_and_paid_as_an_expense(): void
    {
        $d = $this->month(['commission_type' => 'percent', 'commission_value' => '40']);
        $this->assertSame(2, Appointment::where('status', 'completed')->count());
        // 300 + 300 - 100 = 500 taken; 40% = 200
        $this->get('/commission?month=2026-11')->assertOk()->assertSee('40% of the money taken')->assertSee('500.00')->assertSee('200.00');
        $this->get('/commission/'.$d->id.'?month=2026-11')->assertOk()->assertSee('RF-000001');

        $this->from('/commission/'.$d->id)->post('/commission/'.$d->id.'/pay', ['month' => '2026-11', 'amount' => '250', 'method' => 'cash'])->assertSessionHas('danger');
        $this->post('/commission/'.$d->id.'/pay', ['month' => '2026-11', 'amount' => '150', 'method' => 'cash'])->assertSessionHas('success');
        $this->get('/commission?month=2026-11')->assertSee('50.00');   // left to pay
        $paid = Expense::sole();
        $this->assertSame(['commission', '150.00', $d->id, '2026-11'], [$paid->category, $paid->amount, $paid->doctor_id, $paid->commission_month]);
    }

    public function test_an_amount_for_every_patient_seen(): void
    {
        $d = $this->month(['commission_type' => 'per_visit', 'commission_value' => '75']);
        $this->get('/commission/'.$d->id.'?month=2026-11')->assertOk()->assertSee('75.00 for every patient seen')->assertSee('150.00');
        $this->get('/commission?month=2026-10')->assertOk()->assertDontSee('150.00');   // nothing in October
    }

    public function test_expenses_are_kept_voided_never_deleted_and_the_profit_adds_up(): void
    {
        $this->month(['commission_type' => 'none', 'commission_value' => '0']);
        $this->post('/expenses', ['spent_on' => '2026-11-01', 'category' => 'rent', 'amount' => '200', 'method' => 'cash', 'paid_to' => 'Landlord'])->assertSessionHas('success');
        $this->post('/expenses', ['spent_on' => '2026-11-05', 'category' => 'medicines', 'amount' => '50', 'method' => 'cash']);
        $this->post('/expenses', ['spent_on' => '2026-11-06', 'category' => 'other', 'amount' => '999', 'method' => 'cash']);
        $this->from('/expenses')->post('/expenses', ['spent_on' => '2026-12-30', 'category' => 'rent', 'amount' => '1', 'method' => 'cash'])->assertSessionHasErrors('spent_on');
        $this->from('/expenses')->post('/expenses', ['spent_on' => '2026-11-06', 'category' => 'commission', 'amount' => '1', 'method' => 'cash'])->assertSessionHasErrors('category');
        $wrong = Expense::where('amount', '999')->sole();
        $this->post('/expenses/'.$wrong->id.'/void', ['reason' => 'Written twice'])->assertSessionHas('success');
        $this->assertSame(3, Expense::count());

        $this->get('/expenses?month=2026-11')->assertOk()->assertSee('EX-000001')->assertSee('250.00')->assertSee('Written twice');
        // 600 taken, 100 given back, 250 spent: 250 profit
        $this->get('/reports/profit?from=2026-11-01&to=2026-11-30')->assertOk()->assertSee('600.00')->assertSee('100.00')->assertSee('250.00')->assertSee('Rent');

        // the doctor sees no money of the clinic
        $this->actingAs(\App\Models\User::where('email', 'ahmad@acme.test')->sole());
        $this->post('/expenses', ['spent_on' => '2026-11-01', 'category' => 'rent', 'amount' => '1', 'method' => 'cash'])->assertForbidden();
    }
}
