<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A referral letter or a medical certificate: given once, never changed, cancelled with a reason. */
class Letter extends Model
{
    use Audited;

    /** kind => [name, prefix of the number] */
    public const KINDS = [
        'referral' => ['Referral letter', 'REF'],
        'sick_leave' => ['Medical certificate (rest)', 'MC'],
        'fitness' => ['Certificate of fitness', 'FIT'],
        'general' => ['Letter', 'LET'],
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['rest_from' => 'date', 'rest_to' => 'date'];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class)->withTrashed();
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class);
    }

    public function kindLabel(): string
    {
        return __(self::KINDS[$this->kind][0] ?? $this->kind);
    }

    public function restDays(): ?int
    {
        return $this->rest_from && $this->rest_to ? (int) $this->rest_from->diffInDays($this->rest_to) + 1 : null;
    }

    public function signedContent(): string
    {
        return json_encode([$this->number, $this->patient_id, $this->doctor_id, $this->kind, $this->addressed_to, $this->rest_from?->toDateString(), $this->rest_to?->toDateString(),
            $this->body, $this->created_at?->format('Y-m-d H:i:s')], JSON_UNESCAPED_UNICODE);
    }

    public function signatureHolds(): bool
    {
        return $this->signature !== null && hash_equals($this->signature, hash('sha256', $this->signedContent()));
    }

    public function auditLabel(): string
    {
        return $this->number;
    }
}
