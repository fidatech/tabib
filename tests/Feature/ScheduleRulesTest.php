<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The hours of each doctor, his days off, and courses of appointments. (The clock stands at Friday 2026-11-13 10:00.) */
class ScheduleRulesTest extends TestCase
{
    use RefreshDatabase;

    private function setUpDesk(): array
    {
        $this->clinic();
        $this->owner();
        $this->post('/doctors', ['name' => 'Dr. Ahmad', 'email' => 'ahmad@acme.test', 'password' => 'doctor-pass-1', 'fee' => '300', 'colour' => 'teal', 'is_active' => '1']);
        $this->post('/patients', ['name' => 'Gul Ahmad', 'gender' => 'male', 'age' => '40']);

        return [Doctor::sole(), Patient::sole()];
    }

    private function book(Doctor $d, Patient $p, string $date, string $time, array $more = [])
    {
        return $this->from('/appointments/create')->post('/appointments', $more + ['patient_id' => $p->id, 'doctor_id' => $d->id, 'date' => $date, 'time' => $time, 'minutes' => '15']);
    }

    public function test_a_doctor_is_booked_only_in_his_hours_unless_the_desk_says_he_agreed(): void
    {
        [$doctor, $patient] = $this->setUpDesk();
        // Saturday and Monday mornings; Monday also an afternoon; no other day
        $this->put('/doctors/'.$doctor->id.'/schedule', ['days' => [
            6 => [1 => ['from' => '08:00', 'to' => '12:00']],
            1 => [1 => ['from' => '08:00', 'to' => '12:00'], 2 => ['from' => '14:00', 'to' => '17:00']],
        ]])->assertSessionHas('success');
        $this->from('/doctors/'.$doctor->id.'/schedule')->put('/doctors/'.$doctor->id.'/schedule', ['days' => [1 => [1 => ['from' => '08:00', 'to' => '12:00'], 2 => ['from' => '11:00', 'to' => '13:00']]]])
            ->assertSessionHas('danger');

        $this->book($doctor, $patient, '2026-11-14', '09:00')->assertSessionHas('success');          // Saturday morning
        $this->book($doctor, $patient, '2026-11-14', '15:00')->assertSessionHas('danger');           // Saturday afternoon: not his hours
        $this->book($doctor, $patient, '2026-11-15', '09:00')->assertSessionHas('danger');           // Sunday: he does not work
        $this->book($doctor, $patient, '2026-11-16', '14:30')->assertSessionHas('success');          // Monday afternoon
        $this->book($doctor, $patient, '2026-11-16', '13:00')->assertSessionHas('danger');           // between the periods
        $this->book($doctor, $patient, '2026-11-16', '13:00', ['outside_hours' => '1'])->assertSessionHas('success');
        $this->assertTrue((bool) Appointment::where('starts_at', '2026-11-16 13:00:00')->sole()->outside_hours);

        // the calendar shades what he does not work
        $this->get('/appointments?date=2026-11-14&doctor='.$doctor->id)->assertOk()->assertSee('slot-off', false);
    }

    public function test_days_off_close_the_calendar_and_the_queue_of_the_doctor(): void
    {
        [$doctor, $patient] = $this->setUpDesk();
        $this->book($doctor, $patient, '2026-11-17', '09:00')->assertSessionHas('success');
        $this->post('/doctors/'.$doctor->id.'/leaves', ['starts_on' => '2026-11-13', 'ends_on' => '2026-11-18', 'reason' => 'Training in Herat'])
            ->assertSessionHas('warning');   // one appointment is already booked in these days
        $this->book($doctor, $patient, '2026-11-17', '10:00')->assertSessionHas('danger');
        $this->book($doctor, $patient, '2026-11-17', '10:00', ['outside_hours' => '1'])->assertSessionHas('danger');   // on leave: never
        $this->from('/queue')->post('/appointments', ['walk_in' => '1', 'patient_id' => $patient->id, 'doctor_id' => $doctor->id])->assertSessionHas('danger');
        $this->get('/appointments?date=2026-11-17&doctor='.$doctor->id)->assertOk()->assertSee('Training in Herat');
        $this->get('/doctors/'.$doctor->id.'/schedule')->assertOk()->assertSee('Training in Herat');
    }

    public function test_a_course_of_appointments_is_booked_together_and_its_rest_cancelled_together(): void
    {
        [$doctor, $patient] = $this->setUpDesk();
        $this->post('/doctors/'.$doctor->id.'/leaves', ['starts_on' => '2026-11-20', 'ends_on' => '2026-11-20']);
        // physiotherapy every 2 days, 5 times, from Saturday 14: the 20th is a day off and is left out
        $this->book($doctor, $patient, '2026-11-14', '10:00', ['repeat' => 'every', 'repeat_every' => '2', 'repeat_count' => '5'])
            ->assertSessionHas('success')->assertSessionHas('warning');
        $course = Appointment::orderBy('starts_at')->get();
        $this->assertSame(['2026-11-14', '2026-11-16', '2026-11-18', '2026-11-22'], $course->map(fn ($a) => $a->starts_at->toDateString())->all());
        $this->assertSame([$course[0]->id], $course->pluck('series_id')->unique()->values()->all());

        $this->post('/appointments/'.$course[1]->id.'/course/cancel', ['reason' => 'Better'])->assertSessionHas('success');
        $this->assertSame(['booked', 'cancelled', 'cancelled', 'cancelled'], Appointment::orderBy('starts_at')->pluck('status')->all());
    }
}
