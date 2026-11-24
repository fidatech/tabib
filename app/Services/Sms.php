<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\SmsMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Text messages of a clinic. The clinic picks its gateway on the SMS settings page:
 *   log     nothing is sent; messages are only written in the list (to try the words first)
 *   twilio  Twilio (account SID, auth token, sender number)
 *   http    any gateway with a web address: {to} and {message} are put into the address or the body
 * Every message is written in the list, sent or not. Sending never stops the work of the desk.
 */
class Sms
{
    public const GATEWAYS = ['log' => 'Write in the list only (nothing is sent)', 'twilio' => 'Twilio', 'http' => 'Another gateway (web address)'];

    public const DEFAULT_TEXTS = [
        'en' => [
            'reminder' => 'Dear {patient}, a reminder of your appointment with {doctor} at {clinic} on {date} at {time}. To change it call {phone}.',
            'booking' => 'Dear {patient}, your appointment with {doctor} at {clinic} is on {date} at {time}. Call {phone} to change it.',
        ],
        'fa' => [
            'reminder' => '{patient} محترم، یادآوری نوبت شما نزد {doctor} در {clinic}، تاریخ {date} ساعت {time}. برای تغییر به {phone} زنگ بزنید.',
            'booking' => '{patient} محترم، نوبت شما نزد {doctor} در {clinic}، تاریخ {date} ساعت {time} است. برای تغییر به {phone} زنگ بزنید.',
        ],
        'ps' => [
            'reminder' => 'محترم {patient}، د {clinic} کې له {doctor} سره ستاسو د وخت یادونه: {date} ساعت {time}. د بدلون لپاره {phone} ته زنګ ووهئ.',
            'booking' => 'محترم {patient}، د {clinic} کې له {doctor} سره ستاسو وخت {date} ساعت {time} دی. د بدلون لپاره {phone} ته زنګ ووهئ.',
        ],
    ];

    public static function enabled(): bool
    {
        return setting('sms_enabled') === '1';
    }

    /** The words of a message, with the details of the appointment put in. */
    public static function text(string $kind, Appointment $appointment): string
    {
        $lang = in_array(setting('sms_language'), ['en', 'fa', 'ps'], true) ? setting('sms_language') : 'fa';
        $template = setting('sms_text_'.$kind) ?: self::DEFAULT_TEXTS[$lang][$kind];
        $appointment->loadMissing(['patient', 'doctor.user']);
        $clinic = ($lang !== 'en' ? setting('company_name_local') : null) ?: setting('company_name', config('app.name'));
        $date = $lang === 'en' || calendar() !== 'shamsi' ? $appointment->starts_at->format('Y-m-d') : fmt_date($appointment->starts_at);

        return strtr($template, [
            '{patient}' => $appointment->patient?->name ?? '',
            '{doctor}' => $appointment->doctor?->name() ?? '',
            '{clinic}' => $clinic,
            '{date}' => $date,
            '{time}' => $appointment->starts_at->format('H:i'),
            '{phone}' => setting('company_phone', ''),
        ]);
    }

    /** A phone number as the gateway wants it: +93700123456. Null when it cannot be a mobile number. */
    public static function number(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', strtr((string) $phone, ['۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9']));
        $country = preg_replace('/\D+/', '', (string) setting('sms_country_code', '93')) ?: '93';
        if ($digits === '' || strlen($digits) < 8) {
            return null;
        }
        if (str_starts_with((string) $phone, '+') || str_starts_with($digits, '00')) {
            $digits = ltrim($digits, '0');
        } elseif (str_starts_with($digits, '0')) {
            $digits = $country.substr($digits, 1);
        } elseif (! str_starts_with($digits, $country)) {
            $digits = $country.$digits;
        }

        return strlen($digits) >= 10 && strlen($digits) <= 15 ? '+'.$digits : null;
    }

    /** Send (or write) one message. Returns what was kept in the list. */
    public static function send(string $to, string $body, string $kind, ?Patient $patient = null, ?Appointment $appointment = null, ?int $userId = null): SmsMessage
    {
        $message = SmsMessage::create(['patient_id' => $patient?->id, 'appointment_id' => $appointment?->id, 'to' => $to, 'body' => $body, 'kind' => $kind,
            'status' => 'logged', 'created_by' => $userId]);
        $gateway = setting('sms_gateway', 'log');
        if (! self::enabled() || $gateway === 'log') {
            return $message;
        }
        try {
            $id = $gateway === 'twilio' ? self::twilio($to, $body) : self::http($to, $body);
            $message->update(['status' => 'sent', 'provider_id' => $id ? mb_substr($id, 0, 100) : null]);
        } catch (Throwable $e) {
            $message->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 255)]);
        }

        return $message;
    }

    /** The message for a booking, when the clinic sends one. */
    public static function booked(Appointment $appointment, ?int $userId = null): ?SmsMessage
    {
        if (! self::enabled() || setting('sms_on_booking') !== '1' || $appointment->source === 'walk_in') {
            return null;
        }

        return self::forAppointment('booking', $appointment, $userId);
    }

    /**
     * The reminders of the appointments of tomorrow (or of the day given), sent once each, after
     * the hour the clinic chose. Returns how many were sent or written.
     */
    public static function remind(?Carbon $now = null): int
    {
        $now ??= now();
        if (! self::enabled() || setting('sms_reminders') !== '1' || $now->format('H:i') < (setting('sms_reminder_time') ?: '16:00')) {
            return 0;
        }
        $day = $now->copy()->addDay();
        $count = 0;
        Appointment::with(['patient', 'doctor.user'])->whereIn('status', [Appointment::BOOKED, Appointment::CONFIRMED])->whereNull('reminded_at')
            ->whereBetween('starts_at', [$day->copy()->startOfDay(), $day->copy()->endOfDay()])->orderBy('starts_at')
            ->each(function (Appointment $a) use (&$count) {
                $a->forceFill(['reminded_at' => now()])->saveQuietly();   // once, even if the gateway fails
                if (self::forAppointment('reminder', $a)) {
                    $count++;
                }
            });

        return $count;
    }

    private static function forAppointment(string $kind, Appointment $appointment, ?int $userId = null): ?SmsMessage
    {
        $patient = $appointment->patient;
        $to = self::number($patient?->phone);
        if (! $patient || $patient->no_sms || ! $to) {
            return null;
        }

        return self::send($to, self::text($kind, $appointment), $kind, $patient, $appointment, $userId);
    }

    private static function secret(string $key): ?string
    {
        $value = setting($key);
        try {
            return $value ? Crypt::decryptString($value) : null;
        } catch (Throwable) {
            return null;
        }
    }

    private static function twilio(string $to, string $body): ?string
    {
        $sid = (string) setting('sms_twilio_sid');
        $response = Http::asForm()->timeout(15)->withBasicAuth($sid, (string) self::secret('sms_twilio_token'))
            ->post('https://api.twilio.com/2010-04-01/Accounts/'.rawurlencode($sid).'/Messages.json', ['To' => $to, 'From' => setting('sms_sender'), 'Body' => $body]);
        if (! $response->successful()) {
            throw new \RuntimeException('Twilio '.$response->status().': '.($response->json('message') ?? mb_substr($response->body(), 0, 150)));
        }

        return $response->json('sid');
    }

    private static function http(string $to, string $body): ?string
    {
        $url = (string) setting('sms_http_url');
        if (! preg_match('#^https?://#i', $url)) {
            throw new \RuntimeException('The address of the gateway is not set.');
        }
        $fill = fn (string $text, bool $encode) => strtr($text, ['{to}' => $encode ? rawurlencode($to) : $to, '{to_digits}' => ltrim($to, '+'),
            '{message}' => $encode ? rawurlencode($body) : $body, '{sender}' => $encode ? rawurlencode((string) setting('sms_sender')) : (string) setting('sms_sender')]);
        $request = Http::timeout(15);
        if ($token = self::secret('sms_http_token')) {
            $request = $request->withToken($token);
        }
        $response = setting('sms_http_method') === 'post'
            ? $request->withBody(strtr((string) (setting('sms_http_body') ?: '{"to":"{to}","message":"{message}"}'), [
                '{to}' => $to, '{to_digits}' => ltrim($to, '+'), '{message}' => substr((string) json_encode($body, JSON_UNESCAPED_UNICODE), 1, -1),
                '{sender}' => substr((string) json_encode((string) setting('sms_sender'), JSON_UNESCAPED_UNICODE), 1, -1)]), 'application/json')->post($fill($url, true))
            : $request->get($fill($url, true));
        if (! $response->successful()) {
            throw new \RuntimeException('Gateway '.$response->status().': '.mb_substr($response->body(), 0, 150));
        }

        return null;
    }
}
