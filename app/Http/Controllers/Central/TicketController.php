<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\Central\AdminLog;
use App\Models\Central\Ticket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** The support desk: what the clinics asked, answered by the staff. */
class TicketController extends Controller
{
    public function index(Request $request): View
    {
        $status = in_array($request->query('status'), array_keys(Ticket::STATUSES), true) ? $request->query('status') : null;

        return view('central.tickets', [
            'tickets' => Ticket::with('tenant')->when($status, fn ($q) => $q->where('status', $status))
                ->orderByRaw("case status when 'open' then 0 when 'answered' then 1 else 2 end")->orderByDesc('last_message_at')->paginate(25)->withQueryString(),
            'status' => $status, 'counts' => Ticket::selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
        ]);
    }

    public function show(Request $request): View
    {
        return view('central.ticket', ['ticket' => Ticket::with(['tenant', 'messages'])->findOrFail((int) $request->route('id'))]);
    }

    /** An answer, or closing and opening again. */
    public function update(Request $request): RedirectResponse
    {
        $ticket = Ticket::findOrFail((int) $request->route('id'));
        $data = $request->validate(['body' => ['nullable', 'string', 'max:5000'], 'status' => ['nullable', Rule::in(['closed', 'open'])]]);
        if (filled($data['body'] ?? null)) {
            $ticket->say('support', $request->user('admin')->name, $data['body']);
        }
        if (! empty($data['status'])) {
            $ticket->update(['status' => $data['status']]);
        }
        AdminLog::record('ticket', $ticket->tenant, $ticket->number.' '.($data['status'] ?? 'answered'));

        return back()->with('success', __(':number saved.', ['number' => $ticket->number]));
    }
}
