<?php

namespace App\Services;

use App\Exceptions\UserError;
use App\Models\Doctor;
use App\Models\History\Allergy;
use App\Models\History\Medication;
use App\Models\Medicine;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\PrescriptionWarning;
use App\Models\Vital;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The rules of prescribing: the words of the directions in Dari, Pashto and English, the
 * quantity, the checks made before signing (allergies, medicines taken twice, doses for children
 * and for a day), signing and cancelling, and the list of medicines every clinic starts with.
 */
class Prescribing
{
    /** code => [per day (null: as needed), en, fa, ps] */
    public const FREQUENCIES = [
        'od' => [1, 'once a day', 'روزانه یک بار', 'ورځې یو ځل'],
        'bd' => [2, 'twice a day', 'روزانه دو بار', 'ورځې دوه ځله'],
        'tds' => [3, '3 times a day', 'روزانه سه بار', 'ورځې درې ځله'],
        'qid' => [4, '4 times a day', 'روزانه چهار بار', 'ورځې څلور ځله'],
        'q6h' => [4, 'every 6 hours', 'هر ۶ ساعت', 'هر ۶ ساعته'],
        'q8h' => [3, 'every 8 hours', 'هر ۸ ساعت', 'هر ۸ ساعته'],
        'q12h' => [2, 'every 12 hours', 'هر ۱۲ ساعت', 'هر ۱۲ ساعته'],
        'hs' => [1, 'at bedtime', 'وقت خواب', 'د ویده کېدو پر وخت'],
        'prn' => [null, 'when needed', 'در صورت ضرورت', 'د اړتیا پر وخت'],
        'stat' => [1, 'once, now', 'یک بار، همین حالا', 'یو ځل، همدا اوس'],
        'weekly' => [1 / 7, 'once a week', 'هفته یک بار', 'په اونۍ کې یو ځل'],
    ];

    public const TIMINGS = [
        'before_food' => ['before food', 'قبل از غذا', 'له خوړو مخکې'],
        'after_food' => ['after food', 'بعد از غذا', 'له خوړو وروسته'],
        'with_food' => ['with food', 'همراه غذا', 'له خوړو سره'],
        'empty_stomach' => ['on an empty stomach', 'ناشتا', 'په تشه خېټه'],
    ];

    /** unit => [en (one), en (more), fa, ps, counted (the quantity is a number of them)] */
    public const UNITS = [
        'tablet' => ['tablet', 'tablets', 'تابلیت', 'ګولۍ', true],
        'capsule' => ['capsule', 'capsules', 'کپسول', 'کپسول', true],
        'ml' => ['ml', 'ml', 'ملی‌لیتر', 'ملي لیتره', false],
        'mg' => ['mg', 'mg', 'ملی‌گرام', 'ملي ګرامه', false],
        'g' => ['g', 'g', 'گرام', 'ګرامه', false],
        'drop' => ['drop', 'drops', 'قطره', 'څاڅکي', false],
        'puff' => ['puff', 'puffs', 'پف', 'پف', false],
        'sachet' => ['sachet', 'sachets', 'پاکت', 'پاکټ', true],
        'suppository' => ['suppository', 'suppositories', 'شیاف', 'شیاف', true],
        'unit' => ['unit', 'units', 'واحد', 'واحده', false],
        'spray' => ['spray', 'sprays', 'اسپری', 'سپری', false],
        'ampoule' => ['ampoule', 'ampoules', 'امپول', 'امپول', true],
        'vial' => ['vial', 'vials', 'وایل', 'وایل', true],
        'application' => ['application', 'applications', 'بار مالیدن', 'ځله مښل', false],
    ];

    /** When in the day the doses are taken: morning, noon, evening, bedtime (for the picture on the paper). */
    public const SLOTS = [
        'od' => [1, 0, 0, 0], 'bd' => [1, 0, 0, 1], 'tds' => [1, 1, 0, 1], 'qid' => [1, 1, 1, 1], 'q6h' => [1, 1, 1, 1],
        'q8h' => [1, 1, 0, 1], 'q12h' => [1, 0, 0, 1], 'hs' => [0, 0, 0, 1],
    ];

    private const DAYS = ['for :n days', 'برای :n روز', 'د :n ورځو لپاره'];

    /** Allergy words that mean a whole class of medicines. */
    private const CLASS_WORDS = [
        'penicillin' => 'penicillin', 'sulfa' => 'sulfonamide', 'sulpha' => 'sulfonamide', 'sulfonamide' => 'sulfonamide', 'nsaid' => 'nsaid',
        'aspirin' => 'nsaid', 'cephalosporin' => 'cephalosporin', 'quinolone' => 'fluoroquinolone', 'macrolide' => 'macrolide',
        'tetracycline' => 'tetracycline', 'opioid' => 'opioid', 'opiate' => 'opioid', 'codeine' => 'opioid', 'morphine' => 'opioid',
        'benzodiazepine' => 'benzodiazepine', 'aminoglycoside' => 'aminoglycoside', 'statin' => 'statin', 'ace inhibitor' => 'ace inhibitor',
        'پنسلین' => 'penicillin', 'سلفا' => 'sulfonamide',
    ];

    /* ------------------------------------------------------------ words */

    /** "1 tablet, 3 times a day, after food, for 5 days" in English, Dari (fa) or Pashto (ps). */
    public static function directions(PrescriptionItem $item, ?string $locale = null): string
    {
        $locale = in_array($locale, ['fa', 'ps'], true) ? $locale : ($locale === 'en' ? 'en' : (in_array(app()->getLocale(), ['fa', 'ps'], true) ? app()->getLocale() : 'en'));
        $col = ['en' => 0, 'fa' => 1, 'ps' => 2][$locale];
        $parts = [];
        if ($item->dose_amount !== null) {
            $amount = rtrim(rtrim(number_format($item->dose_amount, 3, '.', ''), '0'), '.');
            $unit = self::UNITS[$item->dose_unit] ?? null;
            $word = $unit ? ($col === 0 ? ($item->dose_amount == 1 ? $unit[0] : $unit[1]) : $unit[$col + 1]) : (string) $item->dose_unit;
            $parts[] = self::digits($amount, $locale).' '.$word;
        }
        if ($item->frequency && isset(self::FREQUENCIES[$item->frequency])) {
            $parts[] = self::FREQUENCIES[$item->frequency][$col + 1];
        }
        if ($item->timing && isset(self::TIMINGS[$item->timing])) {
            $parts[] = self::TIMINGS[$item->timing][$col];
        }
        if ($item->days) {
            $parts[] = str_replace(':n', self::digits((string) $item->days, $locale), self::DAYS[$col]);
        }
        if ($item->instructions) {
            $parts[] = $item->instructions;
        }

        return implode($locale === 'en' ? ', ' : '، ', $parts);
    }

    /** Eastern Arabic (Persian) digits for Dari and Pashto. */
    public static function digits(string $text, string $locale): string
    {
        return $locale === 'en' ? $text : strtr($text, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹', '.' => '٫']);
    }

    /** How much to give: tablets are counted ("15 tablets"), liquids added up ("75 ml"); null when it cannot be known. */
    public static function quantity(?float $amount, ?string $unit, ?string $frequency, ?int $days): ?string
    {
        $perDay = self::FREQUENCIES[$frequency][0] ?? null;
        if (! $amount || ! $perDay || ! $days || ! isset(self::UNITS[$unit])) {
            return null;
        }
        $total = $frequency === 'stat' ? $amount : $amount * $perDay * $days;
        if (self::UNITS[$unit][4]) {
            $n = (int) ceil($total - 1e-9);

            return $n.' '.($n === 1 ? self::UNITS[$unit][0] : self::UNITS[$unit][1]);
        }
        if (in_array($unit, ['ml', 'mg', 'g'], true)) {
            return rtrim(rtrim(number_format($total, 1, '.', ''), '0'), '.').' '.$unit;
        }

        return null;
    }

    /* ------------------------------------------------------------ checks */

    /**
     * What the doctor must see before signing. Each warning has a key (to match the reason he gives),
     * a kind and a message.
     *
     * @return list<array{key: string, kind: string, message: string}>
     */
    public static function warnings(Prescription $rx): array
    {
        $rx->loadMissing(['items.medicine', 'patient']);
        $patient = $rx->patient;
        $warnings = [];
        $add = function (string $key, string $kind, string $message) use (&$warnings) {
            $warnings[$key] = ['key' => $key, 'kind' => $kind, 'message' => $message];
        };

        $allergies = Allergy::where('patient_id', $patient->id)->where('status', 'active')->get();
        $taking = Medication::where('patient_id', $patient->id)->where('status', 'active')->get();
        $age = $patient->age();
        $weight = $rx->weight_kg;

        foreach ($rx->items as $item) {
            $name = mb_strtolower($item->name);
            $class = $item->medicine?->class;
            $brands = mb_strtolower((string) $item->medicine?->brands);

            // 1. allergies: the medicine itself, one of its brands, or its whole class
            foreach ($allergies as $allergy) {
                $substance = mb_strtolower(trim($allergy->substance));
                if ($substance === '') {
                    continue;
                }
                $allergyClass = null;
                foreach (self::CLASS_WORDS as $word => $cls) {
                    if (str_contains($substance, $word)) {
                        $allergyClass = $cls;
                    }
                }
                $same = str_contains($name, $substance) || str_contains($substance, $name) || ($brands !== '' && preg_match('/\b'.preg_quote($substance, '/').'\b/u', $brands))
                    || ($class && ($allergyClass === $class || $substance === mb_strtolower($class)));
                if ($same) {
                    $add('allergy-'.$item->id.'-'.$allergy->id, 'allergy', __(':medicine: the patient is allergic to :substance.', ['medicine' => $item->label(), 'substance' => $allergy->substance])
                        .($allergy->reaction ? ' ('.$allergy->reaction.')' : ''));
                } elseif ($allergyClass === 'penicillin' && $class === 'cephalosporin') {
                    $add('allergy-'.$item->id.'-'.$allergy->id, 'allergy', __(':medicine is a cephalosporin; the patient is allergic to penicillin. A cross-reaction is possible.', ['medicine' => $item->label()]));
                }
            }

            // 2. a medicine the patient already takes, or written twice
            foreach ($taking as $med) {
                $taken = mb_strtolower(trim($med->name));
                if ($taken !== '' && (str_contains($taken, $name) || str_contains($name, $taken))) {
                    $add('taking-'.$item->id, 'duplicate', __(':medicine: the patient already takes :taken.', ['medicine' => $item->label(), 'taken' => $med->summary()]));
                }
            }
            foreach ($rx->items as $other) {
                if ($other->id < $item->id && mb_strtolower($other->name) === $name) {
                    $add('twice-'.$item->id, 'duplicate', __(':medicine is written twice.', ['medicine' => $item->name]));
                } elseif ($other->id < $item->id && $class && $other->medicine?->class === $class && ! in_array($class, ['vitamin', 'fluid', 'mineral', 'saline'], true)) {
                    $add('class-'.$item->id, 'duplicate', __(':one and :two are of the same kind (:class).', ['one' => $other->name, 'two' => $item->name, 'class' => __($class)]));
                }
            }

            // 3. doses: for a child by weight, and for a day, against the limits the clinic wrote
            $mg = $item->doseMg();
            $perDay = self::FREQUENCIES[$item->frequency][0] ?? null;
            $childLimit = $item->medicine?->child_max_mg_per_kg_dose;
            if ($childLimit && ($age === null || $age < 12)) {
                if (! $weight) {
                    $add('weight-'.$item->id, 'dose', __(':medicine has a dose limit for children: write the weight of the patient.', ['medicine' => $item->label()]));
                } elseif ($mg !== null && $mg / $weight > $childLimit + 1e-9) {
                    $add('child-'.$item->id, 'dose', __(':medicine: :dose mg is :perkg mg/kg for :weight kg; the limit is :limit mg/kg a dose.', [
                        'medicine' => $item->label(), 'dose' => round($mg, 1), 'perkg' => round($mg / $weight, 1), 'weight' => $weight, 'limit' => $childLimit]));
                }
            }
            $dayLimit = $item->medicine?->max_daily_mg;
            if ($dayLimit && $mg !== null && $perDay && $mg * $perDay > $dayLimit + 1e-9) {
                $add('day-'.$item->id, 'dose', __(':medicine: :total mg a day is more than the limit of :limit mg.', ['medicine' => $item->label(), 'total' => round($mg * $perDay, 1), 'limit' => $dayLimit]));
            }
            if ($item->medicine?->controlled) {
                $add('controlled-'.$item->id, 'controlled', __(':medicine is a controlled medicine.', ['medicine' => $item->label()]));
            }
        }

        return array_values($warnings);
    }

    /* ------------------------------------------------------------ signing */

    /**
     * Sign: every warning needs a reason ($reasons[key]); the prescription gets its number, its
     * signature and the address of its QR code, and its medicines go on the patient's list.
     */
    public static function sign(Prescription $rx, array $reasons, int $userId): Prescription
    {
        return DB::transaction(function () use ($rx, $reasons, $userId) {
            $rx = Prescription::lockForUpdate()->findOrFail($rx->id);
            if (! $rx->isDraft()) {
                throw new UserError(__('This prescription is signed already.'));
            }
            if (! $rx->items()->exists()) {
                throw new UserError(__('Add at least one medicine.'));
            }
            $warnings = self::warnings($rx);
            $missing = array_filter($warnings, fn (array $w) => blank($reasons[$w['key']] ?? null));
            if ($missing) {
                throw new UserError(__('Write why you go on for every warning (:count left).', ['count' => count($missing)]));
            }
            foreach ($warnings as $w) {
                PrescriptionWarning::create(['prescription_id' => $rx->id, 'kind' => $w['kind'], 'message' => mb_substr($w['message'], 0, 500), 'reason' => mb_substr((string) $reasons[$w['key']], 0, 255)]);
            }

            $rx->forceFill(['status' => Prescription::SIGNED, 'number' => Sequence::next('RX'), 'signed_at' => now()->startOfSecond(), 'signed_by' => $userId,
                'verify_token' => Str::lower(Str::random(32))]);
            $rx->signature = hash('sha256', $rx->signedContent());
            $rx->save();

            // the medicines go on the list of what the patient takes (once)
            foreach ($rx->items as $item) {
                $exists = Medication::where('patient_id', $rx->patient_id)->where('status', 'active')->where('name', 'like', $item->name.'%')->exists();
                if (! $exists) {
                    Medication::create([
                        'patient_id' => $rx->patient_id, 'encounter_id' => $rx->encounter_id, 'name' => mb_substr(trim($item->name.' '.$item->strength), 0, 150),
                        'dose' => $item->dose_amount !== null ? rtrim(rtrim(number_format($item->dose_amount, 3, '.', ''), '0'), '.').' '.$item->dose_unit : null,
                        'frequency' => self::FREQUENCIES[$item->frequency][1] ?? null, 'route' => in_array($item->route, ['oral', 'injection', 'topical', 'inhaled'], true) ? $item->route : (in_array($item->route, ['eye', 'ear', 'nasal'], true) ? 'drops' : 'other'),
                        'started_on' => today_iso(), 'stopped_on' => $item->days ? Carbon::parse(today_iso())->addDays($item->days)->toDateString() : null,
                        'note' => $rx->number, 'status' => 'active', 'source' => 'clinician', 'created_by' => $userId, 'updated_by' => $userId,
                    ]);
                }
            }

            return $rx;
        });
    }

    /** A signed prescription that must not be used: it stays, marked cancelled, and its medicines are stopped. */
    public static function cancel(Prescription $rx, string $reason, int $userId): void
    {
        DB::transaction(function () use ($rx, $reason, $userId) {
            $rx->update(['status' => Prescription::CANCELLED, 'cancelled_at' => now(), 'cancel_reason' => $reason]);
            foreach (Medication::where('patient_id', $rx->patient_id)->where('status', 'active')->where('note', $rx->number)->get() as $med) {
                $med->revisionReason = __('Prescription :number cancelled', ['number' => $rx->number]);
                $med->update(['status' => 'stopped', 'stopped_on' => today_iso(), 'stop_reason' => __('Prescription :number cancelled', ['number' => $rx->number]), 'updated_by' => $userId]);
            }
        });
    }

    /** The weight to check doses against: written on the prescription, or taken in the last 30 days. */
    public static function recentWeight(Patient $patient): ?float
    {
        $w = Vital::where('patient_id', $patient->id)->kept()->whereNotNull('weight')->where('taken_at', '>=', now()->subDays(30))->latest('taken_at')->value('weight');

        return $w !== null ? (float) $w : null;
    }

    public static function doctorOf(int $userId): ?Doctor
    {
        return Doctor::where('user_id', $userId)->where('is_active', true)->first();
    }

    /* ------------------------------------------------------------ the list of medicines */

    /**
     * Bring the clinic's medicines up to the shipped list: new rows are added, the words of the
     * shipped rows are refreshed; the clinic's own medicines, its limits and what it switched off stay.
     */
    public static function syncMedicines(?string $file = null): int
    {
        $file ??= database_path('data/medicines.csv');
        if (! is_file($file)) {
            return 0;
        }
        $handle = fopen($file, 'r');
        $header = fgetcsv($handle);
        $known = Medicine::where('is_custom', false)->pluck('id', 'code')->all();
        $new = [];
        $now = now();
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) !== count($header)) {
                continue;
            }
            $r = array_combine($header, $row);
            $values = ['name' => $r['name'], 'strength' => $r['strength'] ?: null, 'form' => $r['form'], 'route' => $r['route'] ?: null, 'class' => $r['class'] ?: null,
                'mg_per_unit' => $r['mg_per_unit'] !== '' ? (float) $r['mg_per_unit'] : null, 'controlled' => $r['controlled'] === '1', 'brands' => $r['brands'] ?: null];
            if (isset($known[$r['code']])) {
                Medicine::whereKey($known[$r['code']])->update($values);
            } else {
                $new[] = $values + ['code' => $r['code'], 'is_custom' => false, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now];
            }
        }
        fclose($handle);
        foreach (array_chunk($new, 200) as $chunk) {
            Medicine::insert($chunk);
        }

        return count($new);
    }
}
