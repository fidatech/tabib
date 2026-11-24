<?php

namespace App\Models\History;

use App\Models\Concerns\HasRevisions;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An entry of the history of a patient (a problem, an allergy, a medicine ...). It is never
 * deleted: it is resolved or stopped, or marked "entered in error" with a reason.
 */
abstract class Entry extends Model
{
    use HasRevisions;

    public const ERROR = 'entered_in_error';

    protected $guarded = [];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class)->withTrashed();
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Everything but what was entered in error. */
    public function scopeKept($query)
    {
        return $query->where('status', '!=', self::ERROR);
    }

    public function isError(): bool
    {
        return $this->status === self::ERROR;
    }

    /** The one line shown in lists and on the timeline. */
    abstract public function summary(): string;
}
