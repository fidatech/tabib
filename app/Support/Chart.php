<?php

namespace App\Support;

use App\Models\Appointment;
use App\Models\Encounter;
use App\Models\History\Allergy;
use App\Models\History\Medication;
use App\Models\History\Problem;
use App\Models\HistoryRevision;
use App\Models\Patient;
use App\Models\PatientDocument;
use App\Models\PatientSocial;
use App\Models\Vital;
use Illuminate\Support\Collection;

/** What each tab of a patient's chart shows. Only the tab that is open is read from the database. */
class Chart
{
    public static function for(Patient $patient, string $tab): array
    {
        return match ($tab) {
            'summary' => self::summary($patient),
            'timeline' => ['events' => self::timeline($patient, request('only'))],
            'history' => self::history($patient),
            'vitals' => self::vitals($patient),
            'visits' => ['encounters' => Encounter::with(['doctor.user', 'diagnoses'])->where('patient_id', $patient->id)->latest('started_at')->get()],
            'documents' => ['documents' => PatientDocument::with('author')->where('patient_id', $patient->id)->latest('id')->get()],
            'prescriptions' => ['prescriptions' => \App\Models\Prescription::with(['doctor.user', 'items'])->where('patient_id', $patient->id)->latest('id')->get()],
            default => [],
        };
    }

    /** The page a doctor reads in thirty seconds. */
    private static function summary(Patient $patient): array
    {
        return [
            'problems' => Problem::where('patient_id', $patient->id)->where('status', 'active')->orderBy('description')->get(),
            'medications' => Medication::where('patient_id', $patient->id)->where('status', 'active')->orderBy('name')->get(),
            'lastVital' => Vital::where('patient_id', $patient->id)->kept()->latest('taken_at')->first(),
            'encounters' => Encounter::with(['doctor.user', 'diagnoses'])->where('patient_id', $patient->id)->latest('started_at')->limit(3)->get(),
        ];
    }

    private static function history(Patient $patient): array
    {
        $sections = [];
        foreach (History::sections() as $key => $def) {
            $entries = $def['model']::with('author')->where('patient_id', $patient->id)
                ->orderByRaw("case status when 'entered_in_error' then 2 when 'active' then 0 else 1 end")->latest('id')->get();
            $sections[$key] = $def + ['key' => $key, 'entries' => $entries];
        }
        // how many times each entry was changed, for the "changes" link
        $revisions = HistoryRevision::with('user')->where('patient_id', $patient->id)->where('action', '!=', 'created')->orderByDesc('id')->get()
            ->groupBy(fn (HistoryRevision $r) => $r->subject_type.'#'.$r->subject_id);

        return ['sections' => $sections, 'social' => PatientSocial::firstOrNew(['patient_id' => $patient->id]), 'revisions' => $revisions];
    }

    /** The vital signs, and for each measure its last values as a small line (drawn in SVG by the view). */
    private static function vitals(Patient $patient): array
    {
        $rows = Vital::with('author')->where('patient_id', $patient->id)->latest('taken_at')->limit(100)->get();
        $kept = $rows->reject(fn (Vital $v) => $v->status === History::ERROR)->sortBy('taken_at')->values();
        $trends = [];
        foreach (['systolic', 'diastolic', 'pulse', 'temperature', 'weight', 'blood_sugar', 'spo2'] as $m) {
            $points = $kept->filter(fn (Vital $v) => $v->{$m} !== null)->take(-20)->map(fn (Vital $v) => ['at' => $v->taken_at, 'value' => (float) $v->{$m}])->values();
            if ($points->count() >= 1) {
                $trends[$m] = $points;
            }
        }

        return ['vitals' => $rows, 'trends' => $trends];
    }

    /**
     * Every event of the patient, newest first: visits, appointments, history entries, vital signs and
     * documents. $only keeps one kind.
     *
     * @return Collection<int, array{at: mixed, kind: string, icon: string, title: string, detail: ?string, url: ?string, error: bool}>
     */
    public static function timeline(Patient $patient, ?string $only = null): Collection
    {
        $events = collect();
        $want = fn (string $kind) => ! $only || $only === $kind;

        if ($want('visits')) {
            foreach (Encounter::with(['doctor.user', 'diagnoses'])->where('patient_id', $patient->id)->get() as $e) {
                $events->push(['at' => $e->started_at, 'kind' => 'visits', 'icon' => 'stethoscope', 'title' => __('Visit with :doctor', ['doctor' => $e->doctor?->name()]),
                    'detail' => $e->diagnoses->map->label()->implode(', ') ?: $e->complaint, 'url' => route('encounters.show', ['id' => $e->id]), 'error' => false,
                    'badge' => $e->isSigned() ? null : __('Draft')]);
            }
        }
        if ($want('appointments')) {
            // an appointment that became a visit is shown once, as the visit
            $visited = Encounter::where('patient_id', $patient->id)->whereNotNull('appointment_id')->pluck('appointment_id');
            foreach (Appointment::with(['doctor.user'])->where('patient_id', $patient->id)->whereNotIn('id', $visited)->get() as $a) {
                $events->push(['at' => $a->starts_at, 'kind' => 'appointments', 'icon' => 'calendar-days', 'title' => __('Appointment with :doctor', ['doctor' => $a->doctor?->name()]),
                    'detail' => $a->reason, 'url' => null, 'error' => false, 'badge' => $a->statusLabel()]);
            }
        }
        if ($want('history')) {
            foreach (History::sections() as $key => $def) {
                foreach ($def['model']::where('patient_id', $patient->id)->get() as $entry) {
                    $events->push(['at' => $entry->created_at, 'kind' => 'history', 'icon' => $def['icon'], 'title' => __($def['singular']).': '.$entry->summary(),
                        'detail' => $entry->isError() ? __('Entered in error').': '.$entry->error_reason : null, 'url' => route('patients.show', ['id' => $patient->id, 'tab' => 'history']).'#h-'.$key,
                        'error' => $entry->isError(), 'badge' => $entry->isError() ? null : __($def['statuses'][$entry->status] ?? $entry->status)]);
                }
            }
        }
        if ($want('prescriptions')) {
            foreach (\App\Models\Prescription::with('items')->where('patient_id', $patient->id)->where('status', '!=', 'draft')->get() as $rx) {
                $events->push(['at' => $rx->signed_at, 'kind' => 'prescriptions', 'icon' => 'prescription-bottle-medical', 'title' => __('Prescription :number', ['number' => $rx->number]),
                    'detail' => $rx->items->map->label()->implode(', '), 'url' => route('prescriptions.show', ['id' => $rx->id]), 'error' => $rx->status === 'cancelled',
                    'badge' => $rx->status === 'cancelled' ? __('Cancelled') : null]);
            }
        }
        if ($want('vitals')) {
            foreach (Vital::where('patient_id', $patient->id)->get() as $v) {
                $events->push(['at' => $v->taken_at, 'kind' => 'vitals', 'icon' => 'heart-pulse', 'title' => __('Vital signs'), 'detail' => $v->summary(),
                    'url' => route('patients.show', ['id' => $patient->id, 'tab' => 'vitals']), 'error' => $v->status === History::ERROR, 'badge' => null]);
            }
        }
        if ($want('documents')) {
            foreach (PatientDocument::where('patient_id', $patient->id)->get() as $d) {
                $events->push(['at' => $d->dated_on ?? $d->created_at, 'kind' => 'documents', 'icon' => 'paperclip', 'title' => __(PatientDocument::KINDS[$d->kind] ?? $d->kind).': '.$d->title,
                    'detail' => null, 'url' => route('patients.show', ['id' => $patient->id, 'tab' => 'documents']), 'error' => $d->status === History::ERROR, 'badge' => null]);
            }
        }

        return $events->sortByDesc(fn (array $e) => $e['at']?->getTimestamp() ?? 0)->values();
    }

    /** The points of a small trend line in a 240 × 60 box, for an SVG polyline. */
    public static function sparkline(Collection $points, int $width = 240, int $height = 60): array
    {
        $values = $points->pluck('value');
        $min = (float) $values->min();
        $max = (float) $values->max();
        $span = $max - $min ?: 1;
        $n = max(1, $points->count() - 1);
        $coords = $points->values()->map(fn (array $p, int $i) => [
            round($points->count() === 1 ? $width / 2 : 6 + $i * ($width - 12) / $n, 1),
            round($height - 8 - (($p['value'] - $min) / $span) * ($height - 16), 1),
        ]);

        return ['points' => $coords->map(fn (array $c) => $c[0].','.$c[1])->implode(' '), 'last' => $coords->last(), 'min' => $min, 'max' => $max];
    }
}
