<?php

namespace App\Http\Controllers\Central;

use App\Exceptions\UserError;
use App\Http\Controllers\Controller;
use App\Models\Central\Admin;
use App\Models\Central\AdminLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/** The staff of Fida Technologies who may sign in to the panel, and what each may do. */
class StaffController extends Controller
{
    public function index(): View
    {
        return view('central.staff', ['staff' => Admin::orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'], 'email' => ['required', 'email', 'max:255', Rule::unique(Admin::class, 'email')],
            'role' => ['required', Rule::in(array_keys(Admin::ROLES))], 'password' => ['required', Password::min(10)],
        ]);
        $admin = Admin::create(['email' => Str::lower($data['email']), 'is_active' => true] + $data);
        AdminLog::record('created', null, 'Staff '.$admin->name.' ('.$admin->role.')');

        return back()->with('success', __(':name saved.', ['name' => $admin->name]));
    }

    public function update(Request $request): RedirectResponse
    {
        $admin = Admin::findOrFail((int) $request->route('id'));
        $data = $request->validate([
            'role' => ['nullable', Rule::in(array_keys(Admin::ROLES))], 'is_active' => ['nullable', 'boolean'], 'password' => ['nullable', Password::min(10)],
        ]);
        $role = $data['role'] ?? $admin->role;
        $active = $request->has('is_active') ? (bool) $data['is_active'] : $admin->is_active;
        // the panel must always keep one owner who can sign in
        $otherOwners = Admin::where('role', 'owner')->where('is_active', true)->where('id', '!=', $admin->id)->count();
        if ($admin->role === 'owner' && $admin->is_active && ($role !== 'owner' || ! $active) && $otherOwners === 0) {
            throw new UserError(__('There must always be one active owner.'));
        }
        $admin->update(array_filter(['role' => $role, 'is_active' => $active, 'password' => $data['password'] ?? null], fn ($v) => $v !== null));
        AdminLog::record('updated', null, 'Staff '.$admin->name.' ('.$admin->role.')');

        return back()->with('success', __(':name saved.', ['name' => $admin->name]));
    }
}
