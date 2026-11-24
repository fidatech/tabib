<?php

namespace App\Http\Controllers;

use App\Models\Central\Invoice;
use App\Models\Central\Payment;
use App\Models\Central\Ticket;
use App\Services\Billing;
use App\Services\Licence;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * What a clinic sees of its service from Fida Technologies, inside its own system: its licence,
 * its invoices and payments, and its questions to the support desk.
 */
class ServiceController extends Controller
{
    public function licence(): View
    {
        Gate::authorize('settings.view');
        $tenant = tenant();

        return view('service.licence', [
            'tenant' => $tenant, 'licence' => Licence::state($tenant), 'balance' => Billing::balance($tenant),
            'invoices' => Invoice::where('tenant_id', $tenant->id)->where('status', '!=', 'cancelled')->orderByDesc('id')->get(),
            'payments' => Payment::where('tenant_id', $tenant->id)->whereNull('cancelled_at')->orderByDesc('paid_on')->orderByDesc('id')->get(),
            'company' => \App\Http\Controllers\Central\SettingsController::company(),
            'subscription' => \App\Services\Subscriptions::of($tenant),   // branch by branch: paid or due
        ]);
    }

    /** One of its own invoices, to print. */
    public function invoice(Request $request): View
    {
        Gate::authorize('settings.view');
        $invoice = Invoice::with('tenant')->where('tenant_id', tenant()->id)->where('status', '!=', 'cancelled')->findOrFail((int) $request->route('id'));

        return view('central.paper', ['kind' => 'invoice', 'invoice' => $invoice, 'payment' => null, 'tenant' => $invoice->tenant, 'balance' => Billing::balance($invoice->tenant)]);
    }

    public function tickets(): View
    {
        Gate::authorize('settings.view');

        return view('service.tickets', ['tickets' => Ticket::where('tenant_id', tenant()->id)->orderByRaw("case status when 'answered' then 0 when 'open' then 1 else 2 end")->orderByDesc('last_message_at')->paginate(20)]);
    }

    public function open(Request $request): RedirectResponse
    {
        Gate::authorize('settings.view');
        $data = $request->validate(['subject' => ['required', 'string', 'max:200'], 'priority' => ['required', Rule::in(array_keys(Ticket::PRIORITIES))], 'body' => ['required', 'string', 'max:5000']]);
        $user = $request->user();

        $ticket = DB::connection((new Ticket)->getConnectionName())->transaction(function () use ($data, $user) {
            $ticket = Ticket::create([
                'tenant_id' => tenant()->id, 'number' => sprintf('TKT-%05d', ((int) Ticket::max('id')) + 1), 'subject' => $data['subject'],
                'priority' => $data['priority'], 'opened_by' => $user->name, 'opened_email' => $user->email,
            ]);
            $ticket->say('client', $user->name, $data['body']);

            return $ticket;
        });

        return redirect()->route('service.ticket', ['id' => $ticket->id])->with('success', __('Your message was sent to the support of Fida Technologies. Its number is :number.', ['number' => $ticket->number]));
    }

    public function ticket(Request $request): View
    {
        Gate::authorize('settings.view');

        return view('service.ticket', ['ticket' => Ticket::with('messages')->where('tenant_id', tenant()->id)->findOrFail((int) $request->route('id'))]);
    }

    /** One more message from the clinic, or closing its own ticket. */
    public function reply(Request $request): RedirectResponse
    {
        Gate::authorize('settings.view');
        $ticket = Ticket::where('tenant_id', tenant()->id)->findOrFail((int) $request->route('id'));
        $data = $request->validate(['body' => ['nullable', 'string', 'max:5000'], 'close' => ['nullable', 'boolean']]);
        if (filled($data['body'] ?? null)) {
            $ticket->say('client', $request->user()->name, $data['body']);
        }
        if ($request->boolean('close')) {
            $ticket->update(['status' => 'closed']);
        }

        return back()->with('success', __('Saved.'));
    }
}
