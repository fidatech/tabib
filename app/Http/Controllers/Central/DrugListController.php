<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\Central\AdminLog;
use App\Models\Central\DrugListEntry;
use App\Models\Central\Tenant;
use App\Models\Medicine;
use App\Services\Prescribing;
use App\Tenancy\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

/**
 * The list of medicines of the platform. Fida Technologies keeps it here; every change is sent to
 * every clinic at once, where it cannot be changed (a clinic writes only its dose limits, switches
 * off what it does not use, and adds its own medicines).
 */
class DrugListController extends Controller
{
    public function index(Request $request): View
    {
        $term = trim((string) $request->query('q', ''));
        $show = (string) $request->query('show', '');
        $edit = $request->query('edit') ? DrugListEntry::find((int) $request->query('edit')) : null;

        return view('central.drugs', [
            'rows' => DrugListEntry::query()->when($term !== '', fn ($q) => $q->search($term))->when($show === 'off', fn ($q) => $q->where('is_active', false))
                ->orderBy('name')->orderBy('form')->orderBy('strength')->paginate(50)->withQueryString(),
            'term' => $term, 'show' => $show, 'edit' => $edit,
            'count' => DrugListEntry::count(), 'off' => DrugListEntry::where('is_active', false)->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->data($request);
        $data['code'] = Str::slug($data['name'].' '.($data['strength'] ?? '').' '.$data['form']);
        if (DrugListEntry::where('code', $data['code'])->exists()) {
            return back()->withInput()->with('danger', __('This medicine, strength and form is in the list already.'));
        }
        $entry = DrugListEntry::create($data + ['is_active' => true]);
        AdminLog::record('medicine added', null, $entry->label());

        return redirect()->route('admin.drugs.index', ['q' => $entry->name])->with('success', __(':name saved.', ['name' => $entry->label()]).' '.$this->send());
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $entry = DrugListEntry::findOrFail($id);
        $entry->update($this->data($request));
        AdminLog::record('medicine changed', null, $entry->label());

        return redirect()->route('admin.drugs.index', ['q' => $request->input('back_q')])->with('success', __(':name saved.', ['name' => $entry->label()]).' '.$this->send());
    }

    /** Take a medicine off the list (it can no longer be prescribed anywhere), or put it back. */
    public function status(Request $request, int $id): RedirectResponse
    {
        $entry = DrugListEntry::findOrFail($id);
        $entry->update(['is_active' => ! $entry->is_active]);
        AdminLog::record($entry->is_active ? 'medicine put back' : 'medicine withdrawn', null, $entry->label());

        return back()->with('success', ($entry->is_active ? __(':name is on the list again.', ['name' => $entry->label()]) : __(':name was taken off the list.', ['name' => $entry->label()])).' '.$this->send());
    }

    public function sync(): RedirectResponse
    {
        return back()->with('success', $this->send());
    }

    /** The list goes to every working clinic now. */
    private function send(): string
    {
        $tenancy = app(Tenancy::class);
        $done = 0;
        $failed = [];
        foreach (Tenant::where('status', Tenant::ACTIVE)->orderBy('id')->get() as $tenant) {
            try {
                $tenancy->run($tenant, fn () => Prescribing::syncMedicines());
                $done++;
            } catch (Throwable $e) {
                report($e);
                $failed[] = $tenant->name;
            }
        }

        return __('Sent to :count clinics.', ['count' => $done]).($failed ? ' '.__('Not sent to: :names (they get it at their next update).', ['names' => implode(', ', $failed)]) : '');
    }

    private function data(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'strength' => ['nullable', 'string', 'max:80'],
            'form' => ['required', Rule::in(Medicine::FORMS)],
            'route' => ['nullable', 'string', 'max:20'],
            'class' => ['nullable', 'string', 'max:60'],
            'mg_per_unit' => ['nullable', 'numeric', 'min:0'],
            'controlled' => ['nullable', 'boolean'],
            'brands' => ['nullable', 'string', 'max:2000', 'regex:/^((PK|IN|TR|US|AF|IR): [^|]+)( \| (PK|IN|TR|US|AF|IR): [^|]+)*$/'],
        ], ['brands.regex' => __('Write the brands as: PK: Panadol, Calpol | IN: Crocin | AF: ...')]);
        $data['controlled'] = $request->boolean('controlled');

        return $data;
    }
}
