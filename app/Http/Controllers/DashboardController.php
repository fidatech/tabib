<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Notice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $branches = Branch::withCount('users')->orderByRaw("case type when 'main' then 0 else 1 end")->orderBy('name')->get();
        $staff = User::where('is_active', true)->count();

        // what a new clinic still has to do before its first patient: each step is done or not, with the page that does it
        $steps = [];
        if ($user->can('settings.view')) {
            $steps[] = ['Add the logo and contact details of the clinic', (bool) (setting('company_logo') && setting('company_phone')), route('settings.index'), 'image'];
            $steps[] = ['Write the health ministry licence number', (bool) setting('licence_no'), route('settings.index'), 'file-medical'];
        }
        if ($user->can('users.view')) {
            $doctors = Role::where('name', 'Doctor')->first();
            $steps[] = ['Add the doctors of the clinic', $doctors && User::role($doctors)->where('is_active', true)->exists(), route('users.create'), 'user-doctor'];
            $steps[] = ['Add the front desk and the nurses', $staff >= 3, route('users.create'), 'users'];
        }

        return view('dashboard', [
            'branches' => $branches,
            'staff' => $staff,
            'doctors' => ($role = Role::where('name', 'Doctor')->first()) ? User::role($role)->where('is_active', true)->count() : 0,
            'steps' => $steps,
            'notices' => Notice::where('user_id', $user->id)->whereNull('read_at')->orderByDesc('id')->limit(5)->get(),
        ]);
    }
}
