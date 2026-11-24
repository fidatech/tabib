<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * A list and a form for papers that post into the journal (an expense, a capital entry,
 * an asset ...). A paper is never edited or deleted: it is cancelled, and stays on the
 * list crossed out. A screen declares its fields and columns and says how to record
 * and how to cancel.
 */
abstract class PaperController extends Controller
{
    protected string $route;

    protected string $permission;

    protected string $title;

    protected string $singular;

    protected string $icon = 'file';

    /** A line under the title of the list. */
    protected ?string $hint = null;

    /** The papers of the list, newest first, of the branch on the screen. */
    abstract protected function rows(Request $request): LengthAwarePaginator;

    /** @return array<string, callable> column label => function (Model $paper) */
    abstract protected function columns(): array;

    /** @return array<int, array<string, mixed>> the same field shape as CrudController */
    abstract protected function fields(): array;

    /** Record the paper; return the message shown afterwards. */
    abstract protected function record(array $data, Request $request): string;

    abstract protected function find(int $id): Model;

    abstract protected function cancel(Model $paper, ?string $reason): void;

    /** Whether the signed-in user may record on this screen now (for example only people of the main branch). */
    protected function mayRecord(): bool
    {
        return true;
    }

    public function index(Request $request): View
    {
        Gate::authorize($this->permission.'.view');

        return view('papers.index', $this->shared() + [
            'rows' => $this->rows($request),
            'columns' => $this->columns(),
            'canManage' => $request->user()->can($this->permission.'.manage') && $this->mayRecord(),
            'branch' => viewed_branch(),
            'branches' => can_oversee() ? \App\Models\Branch::withBooks()->orderBy('id')->pluck('name', 'id')->all() : [],
            'hint' => $this->hint,
        ]);
    }

    public function create(): View
    {
        Gate::authorize($this->permission.'.manage');
        abort_unless($this->mayRecord(), 403);

        return view('papers.form', $this->shared() + ['fields' => $this->fields(), 'record' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize($this->permission.'.manage');
        abort_unless($this->mayRecord(), 403);

        $rules = [];
        $labels = [];
        foreach ($this->fields() as $field) {
            $rules[$field['name']] = $field['rules'] ?? ['nullable'];
            $labels[$field['name']] = mb_strtolower(__($field['label']));
        }
        $message = $this->record($request->validate($rules, [], $labels), $request);

        return redirect()->route($this->route.'.index')->with('success', $message);
    }

    public function destroy(Request $request): RedirectResponse
    {
        Gate::authorize($this->permission.'.manage');
        abort_unless($this->mayRecord(), 403);

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);
        $this->cancel($this->find((int) $request->route('id')), $data['reason'] ?? null);

        return back()->with('success', __(':name cancelled.', ['name' => __($this->singular)]));
    }

    /** The receipt of one paper, ready for the printer: every column of the list, and places to sign. */
    public function receipt(Request $request): View
    {
        Gate::authorize($this->permission.'.view');
        $paper = $this->find((int) $request->route('id'));
        $lines = [];
        foreach ($this->columns() as $label => $column) {
            $lines[$label] = trim(strip_tags((string) $column($paper)));
        }

        return view('papers.receipt', $this->shared() + [
            'lines' => $lines, 'number' => $paper->number ?? null, 'cancelled' => $paper->isCancelled(),
            'branch' => $paper->branch ?? current_branch(),
        ]);
    }

    protected function shared(): array
    {
        return ['title' => __($this->title), 'singular' => __($this->singular), 'icon' => $this->icon, 'route' => $this->route];
    }
}
