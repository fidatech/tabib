<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The day of a clinic: booking, no double booking, the visit step by step, walk-ins and the queue. (The clock stands at 2026-11-13 10:00.) */
class AppointmentTest extends TestCase
{
    use RefreshDatabase;

    private function doctor(string $name = 'Dr. Ahmad', string $email = 'ahmad@acme.test'): Doctor
    {
        $this->post('/doctors', ['name' => $name, 'email' => $email, 'password' => 'doctor-pass-1', 'fee' => '300', 'colour' => 'teal', 'is_active' => '1'])->assertSessionHasNoErrors();

        return Doctor::whereHas('user', fn ($q) => $q->where('email', $email))->sole();
    }

    private function patient(string $name): Patient
    {
        $this->post('/patients', ['name' => $name, 'gender' => 'male', 'age' => '30']);

        return Patient::where('name', $name)->sole();
    }

    private function setUpClinic(): array
    {
        $this->clinic();
        $this->owner();
        $doctor = $this->doctor();
        $this->post('/appointment-types', ['name' => 'First visit', 'minutes' => '20', 'price' => '300', 'is_active' => '1']);

        return [$doctor, AppointmentType::sole(), $this->patient('Gul Ahmad'), $this->patient('Mariam')];
    }

    public function test_an_appointment_is_booked_and_the_doctor_is_never_booked_twice(): void
    {
        [$doctor, $type, $gul, $mariam] = $this->setUpClinic();

        $this->get('/appointments/create?patient='.$gul->id)->assertOk()->assertSee('Gul Ahmad')->assertSee('First visit');
        $this->post('/appointments', ['patient_id' => $gul->id, 'doctor_id' => $doctor->id, 'appointment_type_id' => $type->id, 'date' => '2026-11-13', 'time' => '11:00', 'reason' => 'Fever'])
            ->assertRedirect(route('appointments.index', ['date' => '2026-11-13']))->assertSessionHas('success');
        $a = Appointment::sole();
        $this->assertSame(['2026-11-13 11:00', '2026-11-13 11:20', 'booked', 'desk'], [$a->starts_at->format('Y-m-d H:i'), $a->ends_at->format('Y-m-d H:i'), $a->status, $a->source]);

        // the same time, or a time that overlaps it, is refused with the name of the patient who has it
        $this->from('/appointments/create')->post('/appointments', ['patient_id' => $mariam->id, 'doctor_id' => $doctor->id, 'date' => '2026-11-13', 'time' => '11:10', 'minutes' => '15'])
            ->assertSessionHas('danger', fn ($m) => str_contains($m, 'Gul Ahmad') && str_contains($m, '11:00'));
        // right after it is free
        $this->post('/appointments', ['patient_id' => $mariam->id, 'doctor_id' => $doctor->id, 'date' => '2026-11-13', 'time' => '11:20', 'minutes' => '15'])->assertSessionMissing('danger');
        // another doctor at the same time is free too
        $other = $this->doctor('Dr. Sima', 'sima@acme.test');
        $this->post('/appointments', ['patient_id' => $mariam->id, 'doctor_id' => $other->id, 'date' => '2026-11-13', 'time' => '11:00'])->assertSessionMissing('danger');
        $this->assertSame(3, Appointment::count());

        // not in the past, and with a doctor who sees patients
        $this->post('/appointments', ['patient_id' => $mariam->id, 'doctor_id' => $doctor->id, 'date' => '2026-11-12', 'time' => '09:00'])->assertSessionHasErrors('date');
        $other->update(['is_active' => false]);
        $this->post('/appointments', ['patient_id' => $mariam->id, 'doctor_id' => $other->id, 'date' => '2026-11-14', 'time' => '09:00'])->assertSessionHasErrors('doctor_id');

        // the calendar of the day shows them in the doctor's column; a cancelled one frees its time
        $this->get('/appointments?date=2026-11-13')->assertOk()->assertSee('appt-grid', false)->assertSee('Gul Ahmad')->assertSee('Mariam')->assertSee('Dr. Ahmad');
        $this->post('/appointments/'.$a->id.'/cancel', ['reason' => 'Patient called'])->assertSessionHas('success');
        $this->assertSame(['cancelled', 'Patient called'], [$a->fresh()->status, $a->fresh()->cancel_reason]);
        $this->post('/appointments', ['patient_id' => $mariam->id, 'doctor_id' => $doctor->id, 'date' => '2026-11-13', 'time' => '11:00', 'minutes' => '20'])->assertSessionMissing('danger');
    }

    public function test_an_appointment_is_moved_and_only_while_it_is_open(): void
    {
        [$doctor, $type, $gul, $mariam] = $this->setUpClinic();
        $this->post('/appointments', ['patient_id' => $gul->id, 'doctor_id' => $doctor->id, 'date' => '2026-11-14', 'time' => '09:00']);
        $this->post('/appointments', ['patient_id' => $mariam->id, 'doctor_id' => $doctor->id, 'date' => '2026-11-14', 'time' => '10:00']);
        [$first, $second] = Appointment::orderBy('id')->get()->all();

        $this->get('/appointments/'.$first->id.'/edit')->assertOk()->assertSee('Move the appointment');
        // onto the other one: refused
        $this->from('/appointments/'.$first->id.'/edit')->put('/appointments/'.$first->id, ['patient_id' => $gul->id, 'doctor_id' => $doctor->id, 'date' => '2026-11-14', 'time' => '10:05'])->assertSessionHas('danger');
        // to a free time: moved, and it does not clash with itself
        $this->put('/appointments/'.$first->id, ['patient_id' => $gul->id, 'doctor_id' => $doctor->id, 'date' => '2026-11-14', 'time' => '09:05'])->assertSessionHas('success');
        $this->assertSame('2026-11-14 09:05', $first->fresh()->starts_at->format('Y-m-d H:i'));

        $this->post('/appointments/'.$second->id.'/no_show');
        $this->get('/appointments/'.$second->id.'/edit')->assertForbidden();
        $this->from('/appointments')->post('/appointments/'.$second->id.'/arrive')->assertSessionHas('danger');   // a step that does not follow
    }

    public function test_a_visit_goes_step_by_step_and_the_queue_follows_the_tokens(): void
    {
        [$doctor, $type, $gul, $mariam] = $this->setUpClinic();
        $this->post('/appointments', ['patient_id' => $gul->id, 'doctor_id' => $doctor->id, 'date' => '2026-11-13', 'time' => '10:30']);
        $booked = Appointment::sole();

        $this->post('/appointments/'.$booked->id.'/confirm')->assertSessionHas('success');
        // a walk-in comes first: token 1, in the queue at once, and he takes no time on the calendar
        $this->get('/appointments/create?walk_in=1')->assertOk()->assertSee('Walk-in patient')->assertDontSee('name="time"', false);
        $this->post('/appointments', ['walk_in' => '1', 'patient_id' => $mariam->id, 'doctor_id' => $doctor->id, 'reason' => 'Headache'])->assertRedirect(route('queue.index'));
        $walkIn = Appointment::where('source', 'walk_in')->sole();
        $this->assertSame(['arrived', 1], [$walkIn->status, $walkIn->token]);
        // the booked patient arrives: token 2
        $this->post('/appointments/'.$booked->id.'/arrive')->assertSessionHas('success', fn ($m) => str_contains($m, '2'));
        $this->assertSame(['arrived', 2], [$booked->fresh()->status, $booked->fresh()->token]);
        // a booking at a walk-in's time is still possible
        $this->post('/appointments', ['patient_id' => $gul->id, 'doctor_id' => $doctor->id, 'date' => '2026-11-13', 'time' => '10:00'])->assertSessionMissing('danger');

        $this->get('/queue')->assertOk()->assertSee('Call the next patient')->assertSee('#1')->assertSee('#2')->assertSee('Mariam');

        // the doctor calls: the lowest token comes in first
        $this->post('/queue/'.$doctor->id.'/next')->assertSessionHas('success', fn ($m) => str_contains($m, 'Mariam'));
        $this->assertSame(Appointment::WITH_DOCTOR, $walkIn->fresh()->status);
        // the next call ends the visit before and calls the next token
        $this->post('/queue/'.$doctor->id.'/next')->assertSessionHas('success', fn ($m) => str_contains($m, 'Gul Ahmad'));
        $this->assertSame([Appointment::COMPLETED, Appointment::WITH_DOCTOR], [$walkIn->fresh()->status, $booked->fresh()->status]);
        $this->assertNotNull($walkIn->fresh()->finished_at);
        $this->post('/appointments/'.$booked->id.'/complete')->assertSessionHas('success');
        $this->post('/queue/'.$doctor->id.'/next')->assertSessionHas('info');   // nobody is waiting
        $this->assertSame(Appointment::COMPLETED, $booked->fresh()->status);

        // the patient's page shows the visits; the dashboard counts the day
        $this->get('/patients/'.$mariam->id.'?tab=appointments')->assertOk()->assertSee('Completed')->assertSee('Headache');
        $this->get('/')->assertOk()->assertSee('Appointments today')->assertSee('Seen today');
    }

    public function test_a_doctor_sees_his_own_calendar_and_the_front_desk_books(): void
    {
        [$doctor, $type, $gul] = $this->setUpClinic();
        $other = $this->doctor('Dr. Sima', 'sima@acme.test');
        $this->post('/appointments', ['patient_id' => $gul->id, 'doctor_id' => $other->id, 'date' => '2026-11-13', 'time' => '12:00']);

        // the doctor: his own column first, every doctor on request
        $this->actingAs(User::where('email', 'ahmad@acme.test')->sole());
        $this->get('/queue');   // reads the message of the booking above, so it is not on the next page
        $this->get('/appointments')->assertOk()->assertSee('Dr. Ahmad')->assertDontSee('Gul Ahmad');
        $this->get('/appointments?doctor=all')->assertOk()->assertSee('Gul Ahmad');
        $this->get('/queue')->assertOk()->assertSee('queue-card is-mine', false);

        // the receptionist books, but sees no clinical record
        $desk = $this->userWith(['patients.view', 'patients.manage', 'appointments.view', 'appointments.manage']);
        $this->get('/appointments/create')->assertOk();
        $this->post('/appointments', ['patient_id' => $gul->id, 'doctor_id' => $doctor->id, 'date' => '2026-11-13', 'time' => '12:00'])->assertSessionMissing('danger');
        $this->assertSame($desk->id, Appointment::latest('id')->first()->created_by);

        $this->userWith(['appointments.view']);
        $this->get('/appointments')->assertOk();
        $this->get('/appointments/create')->assertForbidden();
        $this->post('/queue/'.$doctor->id.'/next')->assertForbidden();
    }
}
