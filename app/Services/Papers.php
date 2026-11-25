<?php

namespace App\Services;

use App\Models\Encounter;
use App\Models\Letter;
use App\Models\Patient;

/** The words a letter starts with: in English, Dari or Pashto, with the patient and the visit filled in. The doctor changes them before giving it. */
class Papers
{
    public const TEXTS = [
        'referral' => [
            'en' => "Dear colleague,\n\nI am referring {patient}, {age}, {sex}, for your opinion and management.\n\nComplaint: {complaint}\nFindings: {findings}\nDiagnosis: {diagnosis}\nTreatment given: {treatment}\nAllergies: {allergies}\n\nReason for referral: \n\nThank you for seeing the patient.",
            'fa' => "همکار محترم،\n\n{patient}، {age}، {sex}، را برای نظر و تداوی شما معرفی می‌کنم.\n\nشکایت: {complaint}\nیافته‌ها: {findings}\nتشخیص: {diagnosis}\nتداوی داده‌شده: {treatment}\nحساسیت‌ها: {allergies}\n\nدلیل معرفی: \n\nاز معاینه مریض تشکر.",
            'ps' => "درنښت همکاره،\n\n{patient}، {age}، {sex}، ستاسو د نظر او درملنې لپاره درلېږم.\n\nشکایت: {complaint}\nموندنې: {findings}\nتشخیص: {diagnosis}\nورکړل شوې درملنه: {treatment}\nحساسیتونه: {allergies}\n\nد لېږلو دلیل: \n\nد ناروغ د کتلو مننه.",
        ],
        'sick_leave' => [
            'en' => "This is to certify that {patient}, {age}, was examined by me on {date} and is advised rest for {days} days, from {from} to {to}, because of illness ({diagnosis}).",
            'fa' => "تصدیق می‌شود که {patient}، {age}، به تاریخ {date} توسط اینجانب معاینه شد و به دلیل مریضی ({diagnosis}) برای {days} روز، از {from} تا {to}، به استراحت ضرورت دارد.",
            'ps' => "تصدیق کېږي چې {patient}، {age}، د {date} په نېټه زما لخوا وکتل شو او د ناروغۍ ({diagnosis}) له امله د {days} ورځو لپاره، له {from} څخه تر {to} پورې، استراحت ته اړتیا لري.",
        ],
        'fitness' => [
            'en' => "This is to certify that {patient}, {age}, was examined by me on {date} and found fit, with no condition found that stops him or her from work, study or sport.",
            'fa' => "تصدیق می‌شود که {patient}، {age}، به تاریخ {date} توسط اینجانب معاینه شد و صحتمند یافت شد و مریضی‌ای که مانع کار، تحصیل یا ورزش شود دیده نشد.",
            'ps' => "تصدیق کېږي چې {patient}، {age}، د {date} په نېټه زما لخوا وکتل شو او روغ وموندل شو، او داسې ناروغي ونه لیدل شوه چې د کار، زده کړې یا ورزش مخه ونیسي.",
        ],
        'general' => ['en' => "To whom it may concern,\n\n", 'fa' => "به مقامات مربوطه،\n\n", 'ps' => "اړوندو چارواکو ته،\n\n"],
    ];

    /** The starting words of a letter, filled in from the patient and (when given) the visit. */
    public static function draft(string $kind, string $lang, Patient $patient, ?Encounter $visit, ?string $from = null, ?string $to = null): string
    {
        $visit?->loadMissing(['diagnoses']);
        $sex = ['en' => ['male' => 'male', 'female' => 'female'], 'fa' => ['male' => 'مرد', 'female' => 'زن'], 'ps' => ['male' => 'نارینه', 'female' => 'ښځینه']][$lang][$patient->gender] ?? '';
        $age = $patient->age() !== null ? $patient->age().($lang === 'en' ? ' years' : ($lang === 'fa' ? ' ساله' : ' کلن')) : '';
        $days = $from && $to ? (string) ((int) \Illuminate\Support\Carbon::parse($from)->diffInDays(\Illuminate\Support\Carbon::parse($to)) + 1) : '…';
        $allergies = \App\Models\History\Allergy::where('patient_id', $patient->id)->where('status', 'active')->pluck('substance')->implode(', ');

        return strtr(self::TEXTS[$kind][$lang] ?? self::TEXTS[$kind]['en'], [
            '{patient}' => $patient->name.($patient->father_name ? ($lang === 'en' ? ' son/daughter of ' : ' فرزند ').$patient->father_name : ''),
            '{age}' => $age, '{sex}' => $sex,
            '{date}' => fmt_date($visit?->started_at ?? today_iso()),
            '{complaint}' => (string) $visit?->complaint,
            '{findings}' => trim((string) $visit?->examination),
            '{diagnosis}' => $visit ? $visit->diagnoses->map(fn ($d) => trim(($d->icd10 ? $d->icd10.' ' : '').$d->description))->implode('; ') : '',
            '{treatment}' => trim((string) $visit?->plan),
            '{allergies}' => $allergies ?: ($patient->knowsNoAllergies() ? ($lang === 'en' ? 'none known' : 'معلوم نیست') : '—'),
            '{days}' => $days, '{from}' => $from ? fmt_date($from) : '…', '{to}' => $to ? fmt_date($to) : '…',
        ]);
    }

    public static function issue(array $data, int $doctorId, int $userId): Letter
    {
        $letter = Letter::create($data + ['number' => Sequence::next(Letter::KINDS[$data['kind']][1]), 'doctor_id' => $doctorId, 'status' => 'issued', 'created_by' => $userId]);
        $letter->signature = hash('sha256', $letter->signedContent());
        $letter->saveQuietly();

        return $letter;
    }
}
