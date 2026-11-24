<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clinic();
    }

    public function test_guests_are_sent_to_the_login_page(): void
    {
        $this->get('/')->assertRedirect(route('login'));
        $this->get('/branches')->assertRedirect(route('login'));
    }

    public function test_login_page_loads(): void
    {
        $this->get(route('login'))->assertOk()->assertSee(__('app.auth.submit'));
    }

    public function test_a_wrong_password_is_refused(): void
    {
        $this->post('/login', ['email' => 'owner@acme.test', 'password' => 'not-the-password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_a_user_who_is_switched_off_cannot_sign_in(): void
    {
        User::where('email', 'owner@acme.test')->update(['is_active' => false]);

        $this->post('/login', ['email' => 'owner@acme.test', 'password' => 'password123'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_user_can_sign_in_and_out(): void
    {
        $this->post('/login', ['email' => 'owner@acme.test', 'password' => 'password123'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
        $this->get('/')->assertOk()->assertSee('Acme Clinic');

        $this->post('/logout')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_dari_and_pashto_are_shown_right_to_left(): void
    {
        $this->get('/login')->assertSee('dir="ltr"', false);

        $this->post('/language', ['locale' => 'fa'])->assertRedirect();
        $this->withSession(['locale' => 'fa'])->get('/login')->assertSee('dir="rtl"', false)->assertSee('app.rtl.css', false);
    }
}
