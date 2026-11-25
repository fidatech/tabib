<?php

namespace App\Http\Controllers;

use App\Exceptions\UserError;
use App\Services\PatientImport;
use App\Services\Vault;
use App\Support\SheetReader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Throwable;

/**
 * Bringing patients in from a file, in three steps: the file; which column is which field (guessed
 * from the headings); the check (good rows, rows that look like patients already here, rows with a
 * mistake). The file is kept encrypted until the import is done or given up, never longer.
 */
class PatientImportController extends Controller
{
    private const MAX_ROWS = 5000;

    public function upload(Request $request): View
    {
        Gate::authorize('patients.manage');

        return view('patients.import.upload');
    }

    public function read(Request $request): RedirectResponse
    {
        Gate::authorize('patients.manage');
        $file = $request->validate(['file' => ['required', 'file', 'max:5120', 'mimes:xlsx,csv,txt']])['file'];
        try {
            $rows = SheetReader::read($file->getRealPath(), $file->getClientOriginalName(), self::MAX_ROWS);
        } catch (Throwable $e) {
            throw new UserError($e->getMessage() ?: __('The file could not be read.'));
        }
        if (count($rows) < 2) {
            throw new UserError(__('The file has no patients: a row of headings and at least one row are needed.'));
        }
        if (count($rows) > self::MAX_ROWS) {
            throw new UserError(__('The file has more than :count rows. Split it into smaller files.', ['count' => self::MAX_ROWS]));
        }
        $this->forget($request);
        $request->session()->put('patient_import', ['path' => Vault::putContents(json_encode($rows, JSON_UNESCAPED_UNICODE), 'imports'), 'name' => $file->getClientOriginalName(),
            'map' => PatientImport::guess($rows[0])]);

        return redirect()->route('patients.import.map');
    }

    public function map(Request $request): View|RedirectResponse
    {
        Gate::authorize('patients.manage');
        [$rows, $state] = $this->rows($request);
        if (! $rows) {
            return redirect()->route('patients.import');
        }

        return view('patients.import.map', ['headings' => $rows[0], 'sample' => array_slice($rows, 1, 5), 'map' => $state['map'], 'name' => $state['name'], 'count' => count($rows) - 1]);
    }

    public function setMap(Request $request): RedirectResponse
    {
        Gate::authorize('patients.manage');
        [$rows, $state] = $this->rows($request);
        abort_unless($rows, 404);
        $map = array_filter((array) $request->input('map', []), fn ($f) => isset(PatientImport::FIELDS[$f]));
        if (count($map) !== count(array_unique($map))) {
            throw new UserError(__('Two columns are matched to the same field.'));
        }
        if (! in_array('name', $map, true) || ! in_array('gender', $map, true)) {
            throw new UserError(__('Match at least the name and the sex.'));
        }
        $state['map'] = array_map('strval', $map);
        $request->session()->put('patient_import', $state);

        return redirect()->route('patients.import.check');
    }

    public function check(Request $request): View|RedirectResponse
    {
        Gate::authorize('patients.manage');
        [$rows, $state] = $this->rows($request);
        if (! $rows) {
            return redirect()->route('patients.import');
        }
        $checked = PatientImport::check(array_slice($rows, 1), $state['map']);

        return view('patients.import.check', ['checked' => $checked, 'name' => $state['name'], 'map' => $state['map'],
            'good' => count(array_filter($checked, fn ($r) => ! $r['error'] && ! $r['duplicate'])),
            'duplicates' => count(array_filter($checked, fn ($r) => ! $r['error'] && $r['duplicate'])),
            'errors' => count(array_filter($checked, fn ($r) => (bool) $r['error']))]);
    }

    public function run(Request $request): RedirectResponse
    {
        Gate::authorize('patients.manage');
        [$rows, $state] = $this->rows($request);
        abort_unless($rows, 404);
        $checked = PatientImport::check(array_slice($rows, 1), $state['map']);   // again: the patients may have changed since
        $n = PatientImport::import($checked, $request->boolean('with_duplicates'), $request->user()->id);
        $this->forget($request);

        return redirect()->route('patients.index')->with('success', __(':count patients were registered from the file.', ['count' => $n]));
    }

    public function cancel(Request $request): RedirectResponse
    {
        Gate::authorize('patients.manage');
        $this->forget($request);

        return redirect()->route('patients.index');
    }

    /** @return array{0: ?list<list<string>>, 1: ?array} */
    private function rows(Request $request): array
    {
        $state = $request->session()->get('patient_import');
        $json = $state ? Vault::get($state['path']) : null;

        return $json ? [json_decode($json, true), $state] : [null, null];
    }

    private function forget(Request $request): void
    {
        if ($state = $request->session()->pull('patient_import')) {
            Vault::delete($state['path']);
        }
    }
}
