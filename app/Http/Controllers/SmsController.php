<?php

namespace App\Http\Controllers;

use App\Exceptions\UserError;
use App\Models\Setting;
use App\Models\SmsMessage;
use App\Services\Sms;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** The text messages of the clinic: the gateway, the words, the reminders, and the list of what was sent. */
class SmsController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('settings.view');
        $status = (string) $request->query('status', '');

        return view('sms.index', [
            'messages' => SmsMessage::with('patient')->when(isset(SmsMessage::STATUSES[$status]), fn ($q) => $q->where('status', $status))->latest('id')->paginate(30)->withQueryString(),
            'status' => $status,
            'canManage' => $request->user()->can('settings.manage'),
            'hasToken' => (bool) setting('sms_twilio_token'),
            'hasHttpToken' => (bool) setting('sms_http_token'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        Gate::authorize('settings.manage');
        $data = $request->validate([
            'sms_enabled' => ['nullable', 'boolean'],
            'sms_gateway' => ['required', Rule::in(array_keys(Sms::GATEWAYS))],
            'sms_sender' => ['nullable', 'string', 'max:30'],
            'sms_country_code' => ['required', 'regex:/^\+?\d{1,4}$/'],
            'sms_twilio_sid' => ['nullable', 'required_if:sms_gateway,twilio', 'string', 'max:64'],
            'sms_twilio_token' => ['nullable', 'string', 'max:128'],
            'sms_http_url' => ['nullable', 'required_if:sms_gateway,http', 'regex:/^https?:\/\/[^\s]+$/i', 'max:500'],
            'sms_http_method' => ['nullable', Rule::in(['get', 'post'])],
            'sms_http_body' => ['nullable', 'string', 'max:1000'],
            'sms_http_token' => ['nullable', 'string', 'max:255'],
            'sms_language' => ['required', Rule::in(['en', 'fa', 'ps'])],
            'sms_reminders' => ['nullable', 'boolean'],
            'sms_reminder_time' => ['required', 'date_format:H:i'],
            'sms_on_booking' => ['nullable', 'boolean'],
            'sms_text_reminder' => ['nullable', 'string', 'max:480'],
            'sms_text_booking' => ['nullable', 'string', 'max:480'],
            'online_booking' => ['nullable', 'boolean'],
            'online_days_ahead' => ['nullable', 'integer', 'min:1', 'max:60'],
            'online_min_hours' => ['nullable', 'integer', 'min:0', 'max:72'],
        ]);
        foreach (['sms_enabled', 'sms_reminders', 'sms_on_booking', 'online_booking'] as $flag) {
            $data[$flag] = $request->boolean($flag) ? '1' : '0';
        }
        $data['sms_country_code'] = ltrim($data['sms_country_code'], '+');
        // secrets are kept encrypted, and an empty box keeps the one saved before
        foreach (['sms_twilio_token', 'sms_http_token'] as $secret) {
            if (blank($data[$secret] ?? null)) {
                unset($data[$secret]);
            } else {
                $data[$secret] = Crypt::encryptString($data[$secret]);
            }
        }
        foreach ($data as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value === null || $value === '' ? null : (string) $value]);
        }
        app()->forgetInstance('erp.settings');
        audit('updated', null, 'SMS settings');
        if ($data['online_booking'] === '1' && ! \App\Services\OnlineBooking::open()) {
            return redirect()->route('sms.index')->with('warning', __('Saved. Online booking opens only when text messages are sent through a gateway: patients confirm with a code by SMS.'));
        }

        return redirect()->route('sms.index')->with('success', __('Settings saved.'));
    }

    /** One message to a number, to see that the gateway works. */
    public function test(Request $request): RedirectResponse
    {
        Gate::authorize('settings.manage');
        $to = Sms::number($request->validate(['to' => ['required', 'string', 'max:30']])['to'])
            ?? throw new UserError(__('That is not a mobile number.'));
        $message = Sms::send($to, __('A test message from :clinic.', ['clinic' => setting('company_name', config('app.name'))]), 'test', null, null, $request->user()->id);

        return redirect()->route('sms.index')->with($message->status === 'failed' ? 'danger' : 'success', match ($message->status) {
            'sent' => __('The test message was sent to :to.', ['to' => $to]),
            'failed' => __('The gateway refused the message: :error', ['error' => $message->error]),
            default => __('The message was written in the list only: switch on sending and choose a gateway.'),
        });
    }
}
