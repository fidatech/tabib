<?php

namespace App\Services;

use App\Models\History\Allergy;
use App\Models\Patient;
use App\Support\Shamsi;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Patients brought in from an Excel or CSV file (the register of a clinic, another program):
 * the columns are matched to the fields of a patient, every row is checked, rows that look like a
 * patient already here (or earlier in the file) are kept apart, and the good rows are registered.
 */
class PatientImport
{
    /** field => [label, words a heading may use (en, fa, ps, common)] */
    public const FIELDS = [
        'name' => ['Name', ['name', 'patient', 'patient name', 'full name', 'نام', 'نام مریض', 'اسم', 'نوم', 'د ناروغ نوم']],
        'father_name' => ["Father's name", ['father', "father's name", 'father name', 'نام پدر', 'ولد', 'د پلار نوم', 'پلار']],
        'gender' => ['Sex', ['gender', 'sex', 'جنس', 'جنسیت']],
        'age' => ['Age (years)', ['age', 'سن', 'عمر']],
        'date_of_birth' => ['Date of birth', ['dob', 'date of birth', 'birth date', 'birthday', 'تاریخ تولد', 'د زېږېدو نېټه']],
        'phone' => ['Phone', ['phone', 'mobile', 'telephone', 'tel', 'cell', 'شماره تماس', 'تلفن', 'موبایل', 'شماره', 'تلیفون', 'ټیلیفون']],
        'phone2' => ['Second phone', ['phone 2', 'second phone', 'other phone', 'تلفن دوم']],
        'tazkira' => ['Tazkira number', ['tazkira', 'tazkera', 'id', 'national id', 'nid', 'تذکره', 'شماره تذکره', 'تذکره نمبر']],
        'blood_group' => ['Blood group', ['blood', 'blood group', 'گروپ خون', 'گروه خون', 'د وینې ګروپ']],
        'province' => ['Province', ['province', 'ولایت']],
        'address' => ['Address', ['address', 'آدرس', 'نشانی', 'پته']],
        'allergies' => ['Allergies', ['allergy', 'allergies', 'حساسیت', 'الرژی']],
        'old_number' => ['Number in the old register', ['file', 'file no', 'file number', 'number', 'no', 'mrn', 'record', 'شماره دوسیه', 'نمبر', 'شمېره']],
        'notes' => ['Notes', ['note', 'notes', 'remarks', 'ملاحظات', 'یادداشت', 'یادښت']],
    ];

    private const BLOOD = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];

    /** The field each column of the headings looks like. @return array<int, string> column => field */
    public static function guess(array $headings): array
    {
        $map = [];
        $taken = [];
        foreach ($headings as $i => $h) {
            $h = mb_strtolower(trim(preg_replace('/[\s_.:-]+/u', ' ', (string) $h)));
            foreach (self::FIELDS as $field => [, $words]) {
                if (! isset($taken[$field]) && in_array($h, $words, true)) {
                    $map[$i] = $field;
                    $taken[$field] = true;
                    break;
                }
            }
        }

        return $map;
    }

    /**
     * Every row checked: what will be registered, and why a row is left out.
     *
     * @param  list<list<string>>  $rows  the rows below the headings
     * @param  array<int, string>  $map  column => field
     * @return list<array{line: int, data: array, error: ?string, duplicate: ?string}>
     */
    public static function check(array $rows, array $map, int $firstLine = 2): array
    {
        $existing = Patient::query()->get(['id', 'number', 'name', 'father_name', 'phone', 'phone2', 'tazkira']);
        $byPhone = [];
        $byTazkira = [];
        $byName = [];
        foreach ($existing as $p) {
            foreach ([$p->phone, $p->phone2] as $ph) {
                if ($k = self::phoneKey($ph)) {
                    $byPhone[$k] = $p->number;
                }
            }
            if ($p->tazkira) {
                $byTazkira[self::key($p->tazkira)] = $p->number;
            }
            $byName[self::key($p->name.'|'.$p->father_name)] = $p->number;
        }
        $seen = [];   // keys of the rows above, in this file
        $out = [];
        foreach ($rows as $n => $cells) {
            $line = $firstLine + $n;
            if (! array_filter($cells, fn ($c) => trim((string) $c) !== '')) {
                continue;   // an empty row
            }
            $raw = [];
            foreach ($map as $col => $field) {
                $raw[$field] = trim((string) ($cells[$col] ?? ''));
            }
            [$data, $error] = self::row($raw);
            $duplicate = null;
            if (! $error) {
                $keys = array_filter(['p:'.self::phoneKey($data['phone'] ?? null) => self::phoneKey($data['phone'] ?? null) ? 1 : 0,
                    't:'.self::key($data['tazkira'] ?? '') => ! empty($data['tazkira']) ? 1 : 0, 'n:'.self::key($data['name'].'|'.($data['father_name'] ?? '')) => 1]);
                if (($k = self::phoneKey($data['phone'] ?? null)) && isset($byPhone[$k])) {
                    $duplicate = __('Same phone as :number', ['number' => $byPhone[$k]]);
                } elseif (! empty($data['tazkira']) && isset($byTazkira[self::key($data['tazkira'])])) {
                    $duplicate = __('Same Tazkira as :number', ['number' => $byTazkira[self::key($data['tazkira'])]]);
                } elseif (isset($byName[self::key($data['name'].'|'.($data['father_name'] ?? ''))])) {
                    $duplicate = __("Same name and father's name as :number", ['number' => $byName[self::key($data['name'].'|'.($data['father_name'] ?? ''))]]);
                } else {
                    foreach (array_keys($keys) as $k) {
                        if (isset($seen[$k])) {
                            $duplicate = __('Same as line :line of the file', ['line' => $seen[$k]]);
                            break;
                        }
                    }
                }
                foreach (array_keys($keys) as $k) {
                    $seen[$k] ??= $line;
                }
            }
            $out[] = ['line' => $line, 'data' => $data, 'error' => $error, 'duplicate' => $duplicate];
        }

        return $out;
    }

    /** Register the rows chosen. @return int how many */
    public static function import(array $checked, bool $withDuplicates, int $userId): int
    {
        return DB::transaction(function () use ($checked, $withDuplicates, $userId) {
            $n = 0;
            foreach ($checked as $r) {
                if ($r['error'] || ($r['duplicate'] && ! $withDuplicates)) {
                    continue;
                }
                $d = $r['data'];
                $notes = trim(implode("\n", array_filter([! empty($d['old_number']) ? __('Number in the old register: :n', ['n' => $d['old_number']]) : null, $d['notes'] ?? null])));
                $patient = Patient::create([
                    'number' => Sequence::next('P'), 'name' => $d['name'], 'father_name' => $d['father_name'] ?? null, 'gender' => $d['gender'],
                    'date_of_birth' => $d['date_of_birth'] ?? null, 'dob_estimated' => (bool) ($d['dob_estimated'] ?? false),
                    'phone' => $d['phone'] ?? null, 'phone2' => $d['phone2'] ?? null, 'tazkira' => $d['tazkira'] ?? null, 'blood_group' => $d['blood_group'] ?? null,
                    'province' => $d['province'] ?? null, 'address' => $d['address'] ?? null, 'allergies' => $d['allergies'] ?? null, 'notes' => $notes ?: null, 'created_by' => $userId,
                ]);
                if (! empty($d['allergies']) && ! $patient->knowsNoAllergies()) {
                    foreach (array_filter(array_map('trim', preg_split('/[,،;\n]+/u', $d['allergies']))) as $substance) {
                        Allergy::create(['patient_id' => $patient->id, 'substance' => mb_substr($substance, 0, 150), 'status' => 'active', 'source' => 'patient',
                            'note' => __('From the imported file'), 'created_by' => $userId, 'updated_by' => $userId]);
                    }
                }
                $n++;
            }
            audit('imported', null, $n.' patients from a file');

            return $n;
        });
    }

    /** @return array{0: array, 1: ?string} the fields of a patient, or why the row cannot be one */
    private static function row(array $raw): array
    {
        $d = [];
        $d['name'] = mb_substr(preg_replace('/\s+/u', ' ', $raw['name'] ?? ''), 0, 150);
        if ($d['name'] === '') {
            return [$d, __('No name')];
        }
        $g = mb_strtolower($raw['gender'] ?? '');
        $d['gender'] = match (true) {
            in_array($g, ['m', 'male', 'man', 'boy', 'مرد', 'مذکر', 'پسر', 'نارینه', 'نر', 'هلک'], true) => 'male',
            in_array($g, ['f', 'female', 'woman', 'girl', 'زن', 'مونث', 'مؤنث', 'دختر', 'ښځینه', 'ښځه', 'نجلۍ'], true) => 'female',
            $g === '' => null,
            default => 'other',
        };
        if (! $d['gender']) {
            return [$d, __('No sex (male or female)')];
        }
        foreach (['father_name' => 150, 'phone' => 40, 'phone2' => 40, 'tazkira' => 40, 'province' => 100, 'address' => 255, 'allergies' => 500, 'old_number' => 40, 'notes' => 2000] as $f => $max) {
            if (($raw[$f] ?? '') !== '') {
                $d[$f] = mb_substr(self::digits($raw[$f], $f), 0, $max);
            }
        }
        if (($raw['blood_group'] ?? '') !== '') {
            $bg = strtoupper(str_replace([' ', 'POS', 'NEG', 'VE'], ['', '+', '-', ''], self::digits($raw['blood_group'], 'blood_group')));
            if (in_array($bg, self::BLOOD, true)) {
                $d['blood_group'] = $bg;
            }
        }
        // the date of birth: an Excel number, 2026-01-31, 31/01/2026, or a Shamsi date (1365/05/12); else the age
        if (($raw['date_of_birth'] ?? '') !== '') {
            $dob = self::date(self::digits($raw['date_of_birth'], 'date'));
            if (! $dob) {
                return [$d, __('The date of birth :date is not a date', ['date' => $raw['date_of_birth']])];
            }
            $d['date_of_birth'] = $dob;
        } elseif (($raw['age'] ?? '') !== '') {
            $age = (int) self::digits($raw['age'], 'age');
            if ($age < 0 || $age > 120) {
                return [$d, __('The age :age is not possible', ['age' => $raw['age']])];
            }
            $d['date_of_birth'] = Carbon::parse(today_iso())->subYears($age)->startOfYear()->toDateString();
            $d['dob_estimated'] = true;
        }
        if (! empty($d['date_of_birth']) && $d['date_of_birth'] > today_iso()) {
            return [$d, __('The date of birth is after today')];
        }

        return [$d, null];
    }

    private static function date(string $v): ?string
    {
        if (preg_match('/^\d{4,5}(\.\d+)?$/', $v) && (int) $v > 1000 && (int) $v < 80000) {
            return Carbon::create(1899, 12, 30)->addDays((int) $v)->toDateString();   // a day number of Excel
        }
        if (preg_match('#^(\d{4})[/.-](\d{1,2})[/.-](\d{1,2})#', $v, $m)) {
            if ((int) $m[1] < 1500) {
                return Shamsi::parse(sprintf('%04d/%02d/%02d', $m[1], $m[2], $m[3]));   // a Shamsi year
            }

            return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]) : null;
        }
        if (preg_match('#^(\d{1,2})[/.-](\d{1,2})[/.-](\d{4})$#', $v, $m)) {
            if ((int) $m[3] < 1500) {
                return Shamsi::parse(sprintf('%04d/%02d/%02d', $m[3], $m[2], $m[1]));
            }

            return checkdate((int) $m[2], (int) $m[1], (int) $m[3]) ? sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]) : null;   // day first
        }

        return null;
    }

    /** Persian and Arabic digits become 0-9 in numbers, phones and dates. */
    private static function digits(string $v, string $field): string
    {
        if (in_array($field, ['phone', 'phone2', 'tazkira', 'age', 'date', 'blood_group', 'old_number'], true)) {
            $v = strtr($v, ['۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
                '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9']);
        }
        if (in_array($field, ['phone', 'phone2'], true) && preg_match('/^\d{9}$/', $v)) {
            $v = '0'.$v;   // Excel drops the 0 in front of a mobile number
        }

        return trim($v);
    }

    private static function phoneKey(?string $phone): ?string
    {
        $d = preg_replace('/\D+/', '', (string) $phone);

        return strlen($d) >= 8 ? substr($d, -9) : null;
    }

    private static function key(string $text): string
    {
        return mb_strtolower(preg_replace('/\s+/u', ' ', trim($text)));
    }
}
