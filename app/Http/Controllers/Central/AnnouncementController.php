<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\Central\AdminLog;
use App\Models\Central\Announcement;
use App\Models\Central\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Messages shown on top of the screens of every clinic, or of one. */
class AnnouncementController extends Controller
{
    public function index(): View
    {
        return view('central.announcements', ['announcements' => Announcement::with('tenant')->orderByDesc('id')->paginate(25), 'tenants' => Tenant::orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'tenant_id' => ['nullable', Rule::exists(Tenant::class, 'id')], 'title' => ['required', 'string', 'max:150'], 'body' => ['required', 'string', 'max:2000'],
            'level' => ['required', Rule::in(array_keys(Announcement::LEVELS))], 'starts_on' => ['nullable', 'date'], 'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
        ]);
        $announcement = Announcement::create($data + ['admin_id' => $request->user('admin')->id]);
        AdminLog::record('announced', $announcement->tenant, $announcement->title);

        return back()->with('success', __(':name saved.', ['name' => $announcement->title]));
    }

    /** Show it or stop showing it. */
    public function update(Request $request): RedirectResponse
    {
        $announcement = Announcement::findOrFail((int) $request->route('id'));
        $announcement->update(['is_active' => ! $announcement->is_active]);

        return back()->with('success', __(':name saved.', ['name' => $announcement->title]));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $announcement = Announcement::findOrFail((int) $request->route('id'));
        $announcement->delete();
        AdminLog::record('deleted', null, 'Announcement '.$announcement->title);

        return back()->with('success', __('Deleted.'));
    }
}
