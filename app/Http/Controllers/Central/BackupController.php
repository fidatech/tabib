<?php

namespace App\Http\Controllers\Central;

use App\Exceptions\UserError;
use App\Http\Controllers\Controller;
use App\Models\Central\AdminLog;
use App\Models\Central\Backup;
use App\Models\Central\Tenant;
use App\Services\Backups;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/** Copies of the databases of the clinics: made every night, and by hand; downloaded; put back. */
class BackupController extends Controller
{
    public function index(Request $request): View
    {
        $tenants = Tenant::orderBy('name')->get();
        $chosen = $tenants->firstWhere('id', (int) $request->query('tenant'));

        return view('central.backups', [
            'backups' => Backup::with('tenant')->when($chosen, fn ($q) => $q->where('tenant_id', $chosen->id))->orderByDesc('id')->paginate(30)->withQueryString(),
            'tenants' => $tenants, 'chosen' => $chosen, 'total' => (int) Backup::sum('size'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenant = Tenant::findOrFail((int) $request->validate(['tenant_id' => ['required', 'integer']])['tenant_id']);
        try {
            $backup = Backups::make($tenant, 'manual', $request->user('admin')->id);
        } catch (Throwable $e) {
            report($e);

            throw new UserError(__('The backup could not be made: :reason', ['reason' => $e->getMessage()]));
        }
        AdminLog::record('backup', $tenant, $backup->file);

        return back()->with('success', __('Backup of :name saved.', ['name' => $tenant->name]));
    }

    public function download(Request $request): BinaryFileResponse
    {
        $backup = Backup::with('tenant')->findOrFail((int) $request->route('id'));
        abort_unless(is_file($backup->path()), 404);
        AdminLog::record('backup downloaded', $backup->tenant, $backup->file);

        return response()->download($backup->path(), basename($backup->file));
    }

    /** The short name of the clinic must be typed: what it has now is replaced. */
    public function restore(Request $request): RedirectResponse
    {
        $backup = Backup::with('tenant')->findOrFail((int) $request->route('id'));
        if ($request->input('confirm') !== $backup->tenant->slug) {
            throw new UserError(__('Type the short name of the clinic (:slug) to put this backup back.', ['slug' => $backup->tenant->slug]));
        }
        try {
            Backups::restore($backup, $request->user('admin')->id);
        } catch (Throwable $e) {
            report($e);

            throw new UserError(__('The backup could not be put back: :reason', ['reason' => $e->getMessage()]));
        }
        AdminLog::record('backup restored', $backup->tenant, $backup->file);

        return back()->with('success', __('Backup of :name put back. What it had before was saved as a new backup.', ['name' => $backup->tenant->name]));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $backup = Backup::with('tenant')->findOrFail((int) $request->route('id'));
        Backups::remove($backup);
        AdminLog::record('backup deleted', $backup->tenant, $backup->file);

        return back()->with('success', __('Deleted.'));
    }
}
