<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\Central\AdminLog;
use App\Models\Central\Backup;
use App\Models\Central\Tenant;
use App\Tenancy\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Throwable;

/** Is the service well? Versions, disk, the databases of the clinics, the last errors; and the updates of all of them. */
class HealthController extends Controller
{
    public function index(Tenancy $tenancy): View
    {
        $files = collect(glob(database_path('migrations/tenant/*.php')) ?: [])->map(fn (string $f) => basename($f, '.php'));
        $clients = [];
        foreach (Tenant::where('status', '!=', Tenant::ARCHIVED)->orderBy('name')->get() as $tenant) {
            try {
                $pending = $tenancy->run($tenant, fn () => $files->diff(Schema::hasTable('migrations') ? DB::table('migrations')->pluck('migration') : [])->count());
                $clients[] = ['tenant' => $tenant, 'ok' => true, 'pending' => $pending, 'error' => null];
            } catch (Throwable $e) {
                $clients[] = ['tenant' => $tenant, 'ok' => false, 'pending' => null, 'error' => mb_substr($e->getMessage(), 0, 160)];
            }
        }

        $central = collect(glob(database_path('migrations/*.php')) ?: [])->map(fn (string $f) => basename($f, '.php'));
        $free = @disk_free_space(storage_path()) ?: 0;
        $total = @disk_total_space(storage_path()) ?: 0;
        $last = Backup::max('created_at');

        return view('central.health', [
            'clients' => $clients,
            'facts' => [
                'PHP' => PHP_VERSION, 'Laravel' => app()->version(), 'Environment' => (string) config('app.env'), 'Debug mode' => config('app.debug') ? 'On' : 'Off',
                'Database' => DB::connection($tenancy->central())->getDriverName(), 'Time of the server' => now()->format('Y-m-d H:i').' ('.config('app.timezone').')',
            ],
            'disk' => ['free' => $free, 'total' => $total, 'used' => $total > 0 ? (int) round(($total - $free) / $total * 100) : 0],
            'centralPending' => $central->diff(DB::connection($tenancy->central())->table('migrations')->pluck('migration'))->count(),
            'failedJobs' => Schema::connection($tenancy->central())->hasTable('failed_jobs') ? DB::connection($tenancy->central())->table('failed_jobs')->count() : 0,
            'lastBackup' => $last, 'backupLate' => ! $last || now()->diffInHours($last, true) > 36,
            'errors' => $this->lastErrors(),
            'checks' => [
                'Debug mode is off' => ! config('app.debug'),
                'The storage folder can be written' => is_writable(storage_path('app')),
                'Zip and zlib are installed (Excel files, backups)' => class_exists(\ZipArchive::class) && function_exists('gzopen'),
                'Exact arithmetic is installed (bcmath)' => function_exists('bcadd'),
            ],
        ]);
    }

    /** Bring the tables of every clinic up to date, after a new version of the system was put on the server. */
    public function migrate(): RedirectResponse
    {
        $code = Artisan::call('tenants:migrate');
        AdminLog::record('migrated', null, trim(mb_substr(Artisan::output(), -400)));

        return back()->with($code === 0 ? 'success' : 'danger', $code === 0 ? __('Every clinic is up to date.') : __('Some clinics could not be updated. See the list below.'));
    }

    public function clear(): RedirectResponse
    {
        Artisan::call('optimize:clear');
        AdminLog::record('cleared', null, 'Caches');

        return back()->with('success', __('The caches of the system are cleared.'));
    }

    /** The last errors written by the system, newest first, without their long traces. */
    private function lastErrors(): array
    {
        $file = storage_path('logs/laravel.log');
        if (! is_file($file)) {
            return [];
        }
        $handle = fopen($file, 'rb');
        $size = filesize($file);
        fseek($handle, max(0, $size - 200_000));
        $tail = (string) fread($handle, 200_000);
        fclose($handle);
        preg_match_all('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] \w+\.(ERROR|CRITICAL|EMERGENCY|ALERT): (.*)$/m', $tail, $found, PREG_SET_ORDER);

        return array_map(fn (array $m) => ['at' => $m[1], 'level' => $m[2], 'text' => mb_substr($m[3], 0, 220)], array_slice(array_reverse($found), 0, 12));
    }
}
