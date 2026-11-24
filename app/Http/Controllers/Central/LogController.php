<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\Central\Admin;
use App\Models\Central\AdminLog;
use App\Models\Central\Tenant;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Who did what in the administration panel. */
class LogController extends Controller
{
    public function index(Request $request): View
    {
        $term = trim((string) $request->query('q'));

        return view('central.logs', [
            'logs' => AdminLog::with('admin')
                ->when($request->filled('admin'), fn ($q) => $q->where('admin_id', (int) $request->query('admin')))
                ->when($request->filled('tenant'), fn ($q) => $q->where('tenant_id', (int) $request->query('tenant')))
                ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->query('from')))
                ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->query('to')))
                ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->where('action', 'like', "%$term%")->orWhere('description', 'like', "%$term%")))
                ->orderByDesc('id')->paginate(50)->withQueryString(),
            'admins' => Admin::orderBy('name')->get(), 'tenants' => Tenant::orderBy('name')->pluck('name', 'id'), 'term' => $term,
        ]);
    }
}
