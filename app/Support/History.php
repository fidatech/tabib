<?php

namespace App\Support;

use App\Models\History\Allergy;
use App\Models\History\FamilyHistory;
use App\Models\History\Immunization;
use App\Models\History\Medication;
use App\Models\History\Problem;
use App\Models\History\Surgery;

/**
 * The sections of a patient's history: what each keeps, the boxes of its form and the states an
 * entry can be in. One controller (HistoryController) and one view serve all of them.
 *
 * field: [label, type (text, date, select, textarea, icd10), rules, options for a select]
 */
class History
{
    public const ERROR = 'entered_in_error';

    public static function sections(): array
    {
        return [
            'problems' => [
                'model' => Problem::class, 'title' => 'Problems', 'singular' => 'Problem', 'icon' => 'notes-medical',
                'statuses' => ['active' => 'Active', 'resolved' => 'Resolved'],
                'fields' => [
                    'description' => ['Problem or diagnosis', 'icd10', ['required', 'string', 'max:255']],
                    'icd10' => ['ICD-10 code', 'hidden', ['nullable', 'string', 'max:10']],
                    'onset_on' => ['Since', 'date', ['nullable', 'date', 'before_or_equal:today']],
                    'resolved_on' => ['Resolved on', 'date', ['nullable', 'date', 'before_or_equal:today']],
                    'note' => ['Note', 'text', ['nullable', 'string', 'max:500']],
                ],
            ],
            'allergies' => [
                'model' => Allergy::class, 'title' => 'Allergies', 'singular' => 'Allergy', 'icon' => 'triangle-exclamation',
                'statuses' => ['active' => 'Active', 'inactive' => 'No longer'],
                'fields' => [
                    'substance' => ['Substance', 'text', ['required', 'string', 'max:150'], null, 'A medicine, a food, ...'],
                    'reaction' => ['Reaction', 'text', ['nullable', 'string', 'max:255']],
                    'severity' => ['Severity', 'select', ['nullable', 'in:mild,moderate,severe'], ['mild' => 'Mild', 'moderate' => 'Moderate', 'severe' => 'Severe']],
                    'note' => ['Note', 'text', ['nullable', 'string', 'max:500']],
                ],
            ],
            'medications' => [
                'model' => Medication::class, 'title' => 'Medicines', 'singular' => 'Medicine', 'icon' => 'pills',
                'statuses' => ['active' => 'Taking', 'stopped' => 'Stopped'],
                'fields' => [
                    'name' => ['Medicine', 'text', ['required', 'string', 'max:150']],
                    'dose' => ['Dose', 'text', ['nullable', 'string', 'max:60'], null, 'For example: 500 mg'],
                    'frequency' => ['How often', 'text', ['nullable', 'string', 'max:60'], null, 'For example: 3 times a day'],
                    'route' => ['Route', 'select', ['nullable', 'in:oral,injection,topical,inhaled,drops,other'], ['oral' => 'By mouth', 'injection' => 'Injection', 'topical' => 'On the skin', 'inhaled' => 'Inhaled', 'drops' => 'Drops', 'other' => 'Other']],
                    'started_on' => ['Started on', 'date', ['nullable', 'date']],
                    'stopped_on' => ['Stopped on', 'date', ['nullable', 'date']],
                    'stop_reason' => ['Why it was stopped', 'text', ['nullable', 'string', 'max:255']],
                ],
            ],
            'surgeries' => [
                'model' => Surgery::class, 'title' => 'Operations', 'singular' => 'Operation', 'icon' => 'syringe',
                'statuses' => ['active' => 'Done'],
                'fields' => [
                    'procedure' => ['Operation', 'text', ['required', 'string', 'max:200']],
                    'performed' => ['When', 'text', ['nullable', 'string', 'max:20'], null, 'A date or only a year'],
                    'place' => ['Hospital', 'text', ['nullable', 'string', 'max:150']],
                    'note' => ['Note', 'text', ['nullable', 'string', 'max:500']],
                ],
            ],
            'family' => [
                'model' => FamilyHistory::class, 'title' => 'Family history', 'singular' => 'Family history', 'icon' => 'people-roof',
                'statuses' => ['active' => 'Known'],
                'fields' => [
                    'relative' => ['Relative', 'select', ['required', 'in:father,mother,brother,sister,son,daughter,grandparent,other'],
                        ['father' => 'Father', 'mother' => 'Mother', 'brother' => 'Brother', 'sister' => 'Sister', 'son' => 'Son', 'daughter' => 'Daughter', 'grandparent' => 'Grandparent', 'other' => 'Other']],
                    'condition' => ['Condition', 'text', ['required', 'string', 'max:200']],
                    'note' => ['Note', 'text', ['nullable', 'string', 'max:500']],
                ],
            ],
            'immunizations' => [
                'model' => Immunization::class, 'title' => 'Vaccines', 'singular' => 'Vaccine', 'icon' => 'shield-virus',
                'statuses' => ['active' => 'Given'],
                'fields' => [
                    'vaccine' => ['Vaccine', 'text', ['required', 'string', 'max:150'], null, 'For example: BCG, Polio, Measles, Tetanus'],
                    'dose_no' => ['Dose', 'text', ['nullable', 'string', 'max:20']],
                    'given_on' => ['Given on', 'date', ['nullable', 'date', 'before_or_equal:today']],
                    'batch' => ['Batch number', 'text', ['nullable', 'string', 'max:60']],
                ],
            ],
        ];
    }

    public static function section(string $key): array
    {
        $sections = self::sections();
        abort_unless(isset($sections[$key]), 404);

        return $sections[$key] + ['key' => $key];
    }

    /** The validation rules of a section's form, with the status the entry may be put in. */
    public static function rules(array $section): array
    {
        $rules = [];
        foreach ($section['fields'] as $name => $field) {
            $rules[$name] = $field[2];
        }
        $rules['status'] = ['nullable', 'in:'.implode(',', array_keys($section['statuses']))];
        $rules['source'] = ['nullable', 'in:clinician,patient'];

        return $rules;
    }
}
