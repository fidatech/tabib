<?php

namespace App\Models;

use App\Models\Concerns\HasRevisions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A paper of a patient: a lab report, an X-ray, a referral letter ... kept encrypted (App\Services\Vault). */
class PatientDocument extends Model
{
    use HasRevisions;

    public const KINDS = ['lab' => 'Lab result', 'imaging' => 'X-ray or scan', 'referral' => 'Referral letter', 'consent' => 'Consent', 'prescription' => 'Old prescription', 'other' => 'Other'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['dated_on' => 'date', 'size' => 'integer'];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeKept($query)
    {
        return $query->where('status', '!=', 'entered_in_error');
    }
}
