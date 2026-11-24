<?php

namespace Tests\Feature;

use App\Models\AppointmentType;
use App\Models\Doctor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DoctorTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_owner_adds_a_doctor_who_can_sign_in(): void
    {
        $this->clinic();
        $this->owner();

        $this->get('/doctors')->assertOk();
        $this->get('/doctors/create')->assertOk()->assertSee('Colour on the calendar');
        $this->post('/doctors', ['name' => 'Dr. Ahmad Karimi', 'email' => 'Ahmad@Acme.test', 'phone' => '0700 222 333', 'password' => 'doctor-pass-1',
            'specialty' => 'General practitioner', 'licence_no' => 'MD-1234', 'fee' => '300', 'colour' => 'blue', 'room' => '2', 'is_active' => '1'])
            ->assertRedirect(route('doctors.index'));

        $doctor = Doctor::with('user')->sole();
        $this->assertSame(['Dr. Ahmad Karimi', 'ahmad@acme.test', '0700 222 333', 'blue', '300.00'], [$doctor->name(), $doctor->user->email, $doctor->user->phone, $doctor->colour, $doctor->fee]);
        $this->assertTrue($doctor->user->hasRole('Doctor'));
        $this->assertTrue($doctor->user->can('prescriptions.manage'));
        $this->get('/doctors')->assertSee('Dr. Ahmad Karimi')->assertSee('General practitioner');

        // he signs in with the email and the password written for him
        auth()->logout();
        $this->post('/login', ['email' => 'ahmad@acme.test', 'password' => 'doctor-pass-1'])->assertRedirect(route('dashboard'));

        // edited: the name changes on the user too, and an empty password keeps the old one
        $this->owner();
        $this->put('/doctors/'.$doctor->id, ['name' => 'Dr. Ahmad K.', 'email' => 'ahmad@acme.test', 'specialty' => 'Pediatrician', 'fee' => '350', 'colour' => 'blue', 'is_active' => '1'])
            ->assertRedirect(route('doctors.index'));
        $this->assertSame(['Dr. Ahmad K.', 'Pediatrician'], [$doctor->user->fresh()->name, $doctor->fresh()->specialty]);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('doctor-pass-1', $doctor->user->fresh()->password));
    }

    public function test_a_user_of_the_clinic_can_be_made_a_doctor_and_a_new_doctor_needs_a_password(): void
    {
        $this->clinic();
        $owner = $this->owner();

        // a new email without a password is refused, and nothing is left half made
        $this->from('/doctors/create')->post('/doctors', ['name' => 'Dr. New', 'email' => 'new@acme.test', 'fee' => '0', 'colour' => 'teal', 'is_active' => '1'])->assertSessionHas('danger');
        $this->assertSame(0, Doctor::count());
        $this->assertNull(User::where('email', 'new@acme.test')->first());

        // the owner is a doctor himself: his own user gets the profile, he stays Administrator
        $this->post('/doctors', ['name' => $owner->name, 'email' => $owner->email, 'fee' => '500', 'colour' => 'teal', 'is_active' => '1'])->assertSessionHasNoErrors();
        $this->assertSame($owner->id, Doctor::sole()->user_id);
        $this->assertTrue($owner->fresh()->isAdmin());
        // and only once
        $this->from('/doctors/create')->post('/doctors', ['name' => $owner->name, 'email' => $owner->email, 'fee' => '500', 'colour' => 'teal', 'is_active' => '1'])->assertSessionHas('danger');
        $this->assertSame(1, Doctor::count());
    }

    public function test_appointment_types_are_set_by_those_who_manage_the_settings(): void
    {
        $this->clinic();
        $this->owner();

        $this->get('/appointment-types/create')->assertOk();
        $this->post('/appointment-types', ['name' => 'First visit', 'name_local' => 'معاینه اول', 'minutes' => '20', 'price' => '300', 'is_active' => '1'])->assertRedirect(route('appointment-types.index'));
        $this->post('/appointment-types', ['name' => 'Too short', 'minutes' => '2', 'price' => '0'])->assertSessionHasErrors('minutes');
        $this->assertSame([20, '300.00'], [AppointmentType::sole()->minutes, AppointmentType::sole()->price]);
        $this->get('/appointment-types')->assertOk()->assertSee('First visit');

        $this->userWith(['appointments.manage', 'appointments.view']);
        $this->get('/appointment-types')->assertForbidden();
        $this->get('/doctors')->assertForbidden();
    }
}
