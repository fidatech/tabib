<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A line added to a signed visit: a result that came later, a correction. Never changed. */
class EncounterAddendum extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'encounter_addenda';

    protected $guarded = [];

    protected $casts = ['created_at' => 'datetime'];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
