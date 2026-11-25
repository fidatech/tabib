<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * The structured parts of a visit: the tooth chart (FDI numbers, permanent teeth) and the
 * antenatal form (with the due date and the weeks of pregnancy worked out from the last period).
 */
class Forms
{
    /** The upper jaw right to left as the dentist looks at the patient, then the lower jaw. */
    public const TEETH = [
        [18, 17, 16, 15, 14, 13, 12, 11, 21, 22, 23, 24, 25, 26, 27, 28],
        [48, 47, 46, 45, 44, 43, 42, 41, 31, 32, 33, 34, 35, 36, 37, 38],
    ];

    /** state => [label, short mark] */
    public const TOOTH_STATES = [
        'caries' => ['Caries', 'C'], 'filled' => ['Filled', 'F'], 'missing' => ['Missing', 'M'], 'crown' => ['Crown', 'Cr'],
        'root_canal' => ['Root canal', 'RC'], 'extract' => ['To extract', 'X'], 'implant' => ['Implant', 'I'], 'mobile' => ['Loose', 'L'],
    ];

    public const PRESENTATIONS = ['cephalic' => 'Head down (cephalic)', 'breech' => 'Breech', 'transverse' => 'Transverse', 'unknown' => 'Not known yet'];

    public const GRADES = ['neg' => '—', 'trace' => 'trace', '+' => '+', '++' => '++', '+++' => '+++'];

    /** Validation of the form of a kind of visit. */
    public static function rules(?string $kind): array
    {
        return match ($kind) {
            'dental' => [
                'extra.teeth' => ['nullable', 'array'],
                'extra.teeth.*' => ['nullable', 'in:'.implode(',', array_keys(self::TOOTH_STATES))],
                'extra.teeth_note' => ['nullable', 'string', 'max:1000'],
            ],
            'antenatal' => [
                'extra.lmp' => ['nullable', 'date', 'before_or_equal:today'],
                'extra.gravida' => ['nullable', 'integer', 'min:1', 'max:25'],
                'extra.para' => ['nullable', 'integer', 'min:0', 'max:25'],
                'extra.abortions' => ['nullable', 'integer', 'min:0', 'max:25'],
                'extra.fundal_height' => ['nullable', 'numeric', 'min:5', 'max:50'],
                'extra.fetal_heart' => ['nullable', 'integer', 'min:60', 'max:220'],
                'extra.presentation' => ['nullable', 'in:'.implode(',', array_keys(self::PRESENTATIONS))],
                'extra.movements' => ['nullable', 'in:yes,no'],
                'extra.urine_protein' => ['nullable', 'in:'.implode(',', array_keys(self::GRADES))],
                'extra.urine_sugar' => ['nullable', 'in:'.implode(',', array_keys(self::GRADES))],
                'extra.oedema' => ['nullable', 'in:'.implode(',', array_keys(self::GRADES))],
            ],
            default => [],
        };
    }

    /** What is kept: empty boxes are dropped, so the signature does not change for nothing. */
    public static function clean(?string $kind, array $extra): ?array
    {
        if ($kind === 'dental') {
            $teeth = array_filter((array) ($extra['teeth'] ?? []), fn ($s, $t) => filled($s) && in_array((int) $t, array_merge(...self::TEETH), true), ARRAY_FILTER_USE_BOTH);
            ksort($teeth);
            $out = array_filter(['teeth' => $teeth ?: null, 'teeth_note' => $extra['teeth_note'] ?? null], fn ($v) => filled($v));
        } elseif ($kind === 'antenatal') {
            $keys = ['lmp', 'gravida', 'para', 'abortions', 'fundal_height', 'fetal_heart', 'presentation', 'movements', 'urine_protein', 'urine_sugar', 'oedema'];
            $out = array_filter(array_intersect_key($extra, array_flip($keys)), fn ($v) => filled($v));
            ksort($out);
        } else {
            return null;
        }

        return $out ?: null;
    }

    /** Due date and the age of the pregnancy on a day, from the first day of the last period (Naegele: + 280 days). */
    public static function pregnancy(?string $lmp, Carbon $on): ?array
    {
        if (! $lmp) {
            return null;
        }
        $start = Carbon::parse($lmp)->startOfDay();
        $days = (int) $start->diffInDays($on->copy()->startOfDay(), false);
        if ($days < 0 || $days > 310) {
            return null;
        }

        return ['edd' => $start->copy()->addDays(280)->toDateString(), 'weeks' => intdiv($days, 7), 'days' => $days % 7];
    }
}
