<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A prescription. A draft while the doctor writes it; signed (and numbered, locked, with the
 * address of its QR code) when he signs; cancelled when it must not be used any more.
 */
class Prescription extends Model
{
    use Audited;

    public const DRAFT = 'draft';

    public const SIGNED = 'signed';

    public const CANCELLED = 'cancelled';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['signed_at' => 'datetime', 'cancelled_at' => 'datetime', 'weight_kg' => 'float'];
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

    public function signer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signed_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PrescriptionItem::class)->orderBy('position')->orderBy('id');
    }

    public function warnings(): HasMany
    {
        return $this->hasMany(PrescriptionWarning::class);
    }

    public function isDraft(): bool
    {
        return $this->status === self::DRAFT;
    }

    public function isSigned(): bool
    {
        return $this->status === self::SIGNED;
    }

    public function signedContent(): string
    {
        return json_encode([
            'number' => $this->number, 'patient' => (int) $this->patient_id, 'doctor' => (int) $this->doctor_id, 'weight' => $this->weight_kg,
            'items' => $this->items()->get()->map(fn (PrescriptionItem $i) => [$i->name, $i->strength, $i->form, $i->dose_amount, $i->dose_unit, $i->frequency, $i->timing, $i->days, $i->quantity, $i->instructions])->all(),
            'notes' => $this->notes, 'signed_at' => $this->signed_at?->format('Y-m-d H:i:s'), 'signed_by' => (int) $this->signed_by,
        ], JSON_UNESCAPED_UNICODE);
    }

    public function signatureHolds(): bool
    {
        return $this->status === self::DRAFT || hash_equals((string) $this->signature, hash('sha256', $this->signedContent()));
    }

    /** The address printed in the QR code: anyone (a pharmacist) can check the prescription there. */
    public function verifyUrl(): ?string
    {
        return $this->verify_token ? route('rx.verify', ['token' => $this->verify_token]) : null;
    }

    public function auditLabel(): string
    {
        return ($this->number ?? __('Draft prescription')).' '.$this->patient?->name;
    }
}
