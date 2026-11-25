<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A visit of a patient with a doctor: the note of the visit (complaint, history, examination,
 * assessment, plan), its diagnoses and vital signs. It is a draft until the doctor signs it;
 * a signed visit is never changed again: what comes later is added as an addendum.
 */
class Encounter extends Model
{
    use Audited;

    public const DRAFT = 'draft';

    public const SIGNED = 'signed';

    /** The parts of the note, in the order they are written. */
    public const PARTS = [
        'complaint' => 'Chief complaint',
        'history' => 'History of the present illness',
        'examination' => 'Examination',
        'assessment' => 'Assessment',
        'plan' => 'Plan',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'signed_at' => 'datetime', 'follow_up_on' => 'date', 'extra' => 'array', 'history_reviewed_at' => 'datetime'];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class)->withTrashed();
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'history_reviewed_by');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(NoteTemplate::class);
    }

    public function signer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signed_by');
    }

    public function diagnoses(): HasMany
    {
        return $this->hasMany(EncounterDiagnosis::class)->orderByDesc('is_primary')->orderBy('id');
    }

    public function addenda(): HasMany
    {
        return $this->hasMany(EncounterAddendum::class)->orderBy('id');
    }

    public function vitals(): HasMany
    {
        return $this->hasMany(Vital::class);
    }

    public function isSigned(): bool
    {
        return $this->status === self::SIGNED;
    }

    /** What the signature covers: the note, the diagnoses, who signed and when. */
    public function signedContent(): string
    {
        return json_encode([
            'id' => (int) $this->id, 'patient' => (int) $this->patient_id, 'doctor' => (int) $this->doctor_id,
            'note' => array_map(fn (string $part) => (string) $this->{$part}, array_combine(array_keys(self::PARTS), array_keys(self::PARTS))),
            'follow_up' => $this->follow_up_on?->toDateString(),
            'diagnoses' => $this->diagnoses()->get()->map(fn (EncounterDiagnosis $d) => [(string) $d->icd10, (string) $d->description, (bool) $d->is_primary])->all(),
            'signed_at' => $this->signed_at?->format('Y-m-d H:i:s'), 'signed_by' => (int) $this->signed_by,
        ] + ($this->extra ? ['form' => [(string) $this->kind, $this->extra]] : [])
          + ($this->history_reviewed_at ? ['reviewed' => [$this->history_reviewed_at->format('Y-m-d H:i:s'), (int) $this->history_reviewed_by]] : []), JSON_UNESCAPED_UNICODE);   // the tooth chart or pregnancy form, when there is one
    }

    /** Was the note changed after it was signed (by hand, in the database)? */
    public function signatureHolds(): bool
    {
        return ! $this->isSigned() || hash_equals((string) $this->signature, hash('sha256', $this->signedContent()));
    }

    public function auditLabel(): string
    {
        return __('Visit').' '.$this->started_at?->format('Y-m-d').' '.$this->patient?->name;
    }
}
