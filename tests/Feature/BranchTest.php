<?php

namespace Tests\Feature;

use App\Models\Branch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchTest extends TestCase
{
    use RefreshDatabase;

    private function form(array $over = []): array
    {
        $data = $over + ['name' => 'Herat', 'code' => 'hrt', 'country' => 'Afghanistan', 'province' => 'Herat', 'city' => 'Herat', 'is_active' => '1'];

        // a new branch may come with its first user
        return $data + ['user_name' => 'Admin of '.$data['name'], 'user_email' => strtolower($data['code']).'.'.strtolower(str_replace(' ', '', $data['name'])).'@acme.test', 'user_password' => 'password123', 'user_role' => 'Clinic Admin'];
    }

    public function test_the_owner_adds_changes_and_removes_a_branch(): void
    {
        $this->clinic();
        $this->owner();

        $this->get('/branches')->assertOk()->assertSee('Main branch');
        $this->get('/branches/create')->assertOk();
        $this->post('/branches', $this->form())->assertRedirect(route('branches.index'));

        $branch = Branch::where('code', 'HRT')->firstOrFail();
        $this->assertSame(Branch::DEPENDENT, $branch->type);
        $this->assertSame(['Afghanistan', 'Herat'], [$branch->country, $branch->province]);
        // its first user was made on the same page
        $admin = \App\Models\User::where('email', 'hrt.herat@acme.test')->firstOrFail();
        $this->assertSame($branch->id, $admin->branch_id);
        $this->assertTrue($admin->hasRole('Clinic Admin'));
        // the first user is not needed, but half of one is refused
        $this->post('/branches', ['name' => 'Ghazni', 'code' => 'gzn', 'country' => 'Afghanistan', 'user_email' => 'g@acme.test'])->assertSessionHasErrors(['user_name', 'user_password', 'user_role']);
        $this->post('/branches', ['name' => 'Kandahar', 'code' => 'kdr', 'country' => 'Afghanistan', 'is_active' => '1'])->assertSessionHasNoErrors();
        $this->post('/branches', $this->form(['name' => 'Mars', 'code' => 'mrs', 'country' => 'Mars']))->assertSessionHasErrors('country');

        $this->get('/branches/'.$branch->id.'/edit')->assertOk();
        $this->put('/branches/'.$branch->id, $this->form(['name' => 'Herat City', 'representative_name' => 'Dr. Ahmad']))->assertRedirect(route('branches.index'));
        $this->assertSame('Herat City', $branch->fresh()->name);
        $this->assertSame('Dr. Ahmad', $branch->fresh()->representative_name);

        // a branch with users is not removed; without them it is
        $this->from('/branches')->delete('/branches/'.$branch->id)->assertSessionHas('danger');
        $admin->delete();
        $this->delete('/branches/'.$branch->id)->assertRedirect(route('branches.index'));
        $this->assertNull(Branch::find($branch->id));
    }

    public function test_the_main_branch_stays(): void
    {
        $this->clinic();
        $this->owner();
        $main = Branch::where('type', Branch::MAIN)->firstOrFail();

        $this->from('/branches')->delete('/branches/'.$main->id)->assertRedirect('/branches')->assertSessionHas('danger');
        $this->assertNotNull(Branch::find($main->id));

        // it stays the main branch when it is changed
        $this->put('/branches/'.$main->id, ['name' => 'Head office', 'code' => 'MAIN', 'type' => Branch::DEPENDENT]);
        $this->assertSame(Branch::MAIN, $main->fresh()->type);
        $this->assertSame('Head office', $main->fresh()->name);
    }

    public function test_the_plan_limits_the_branches(): void
    {
        $this->clinic(['max_branches' => 1]);
        $this->owner();

        $this->post('/branches', $this->form())->assertSessionMissing('danger');
        $this->from('/branches/create')->post('/branches', $this->form(['name' => 'Paktia', 'code' => 'PKT']))->assertSessionHas('danger');
        $this->assertSame(1, Branch::where('type', Branch::DEPENDENT)->count());

        // a branch that is switched off does not count
        $this->post('/branches', $this->form(['name' => 'Old place', 'code' => 'OLD', 'is_active' => null, 'user_name' => null, 'user_email' => null, 'user_password' => null, 'user_role' => null]))->assertSessionMissing('danger');
        $this->assertSame(2, Branch::where('type', Branch::DEPENDENT)->count());
    }

    public function test_a_code_is_used_once(): void
    {
        $this->clinic();
        $this->owner();

        $this->post('/branches', $this->form());
        $this->post('/branches', $this->form(['name' => 'Another']))->assertSessionHasErrors('code');
    }

    public function test_permissions_are_respected(): void
    {
        $this->clinic();

        $this->userWith(['branches.view']);
        $this->get('/branches')->assertOk();
        $this->get('/branches/create')->assertForbidden();
        $this->post('/branches', $this->form())->assertForbidden();

        $this->userWith([]);
        $this->get('/branches')->assertForbidden();
        $this->get('/')->assertOk();
    }
}
