<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use App\Services\Vault;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(): View
    {
        Gate::authorize('settings.view');

        return view('settings.index', ['fonts' => array_map(fn (array $font) => $font['name'], config('fonts'))]);
    }

    public function update(Request $request): RedirectResponse
    {
        Gate::authorize('settings.manage');

        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:150'],
            'company_name_local' => ['nullable', 'string', 'max:150'],
            'licence_no' => ['nullable', 'string', 'max:60'],
            'company_phone' => ['nullable', 'string', 'max:50'],
            'company_email' => ['nullable', 'email', 'max:150'],
            'company_address' => ['nullable', 'string', 'max:255'],
            'calendar' => ['sometimes', 'required', Rule::in(['shamsi', 'gregorian'])],
            'font_fa' => ['nullable', Rule::in(array_keys(config('fonts')))],
            'font_ps' => ['nullable', Rule::in(array_keys(config('fonts')))],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024'],
            'remove_logo' => ['nullable', 'boolean'],
            'clinic_licence' => ['nullable', 'file', 'mimes:pdf,png,jpg,jpeg,webp', 'max:5120'],
            'remove_clinic_licence' => ['nullable', 'boolean'],
            'opening_time' => ['required', 'date_format:H:i'],
            'closing_time' => ['required', 'date_format:H:i', 'after:opening_time'],
            'slot_minutes' => ['required', 'integer', Rule::in([5, 10, 15, 20, 30, 45, 60])],
            'currency' => ['required', 'string', 'max:5'],
        ]);

        // the logo is kept in the clinic's own folder, outside the public folder
        $logo = $data['logo'] ?? null;
        $remove = ! empty($data['remove_logo']);
        unset($data['logo'], $data['remove_logo']);
        if ($logo || $remove) {
            if ($old = setting('company_logo')) {
                Storage::disk('local')->delete($old);
            }
            $data['company_logo'] = $logo ? $logo->storeAs('logos/'.tenant()->id, 'logo-'.time().'.'.$logo->extension(), 'local') : null;
        }

        // the licence of the Ministry of Public Health is kept encrypted, like the papers of patients
        $licence = $data['clinic_licence'] ?? null;
        $dropLicence = ! empty($data['remove_clinic_licence']);
        unset($data['clinic_licence'], $data['remove_clinic_licence']);
        if ($licence || $dropLicence) {
            if ($old = setting('licence_file')) {
                Vault::delete($old);
            }
            $data['licence_file'] = $licence ? Vault::put($licence, 'licence') : null;
            $data['licence_name'] = $licence ? mb_substr($licence->getClientOriginalName(), 0, 200) : null;
            $data['licence_mime'] = $licence ? $licence->getMimeType() : null;
        }

        foreach ($data as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value === null ? null : (string) $value]);
        }
        app()->forgetInstance('erp.settings');
        audit('updated', null, 'Settings');

        return redirect()->route('settings.index')->with('success', __('Settings saved.'));
    }

    /** The logo of the clinic, for the menu, the sign-in page and printed papers. */
    public function logo(): \Symfony\Component\HttpFoundation\Response
    {
        $path = setting('company_logo');
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return response(Storage::disk('local')->get($path), 200, [
            'Content-Type' => Storage::disk('local')->mimeType($path) ?: 'image/png',
            'Cache-Control' => 'public, max-age=604800',   // its address changes with the logo (?v=...)
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** The licence of the Ministry of Public Health, opened by those who may see the settings. */
    public function licence(): \Symfony\Component\HttpFoundation\Response
    {
        Gate::authorize('settings.view');
        $contents = setting('licence_file') ? Vault::get(setting('licence_file')) : null;
        abort_if($contents === null, 404);
        audit('viewed', null, 'Clinic licence');

        return response($contents, 200, [
            'Content-Type' => setting('licence_mime') ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.addcslashes((string) setting('licence_name', 'licence'), '"\\').'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
