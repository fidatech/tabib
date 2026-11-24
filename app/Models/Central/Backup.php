<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One saved copy of the database of a clinic. */
class Backup extends Model
{
    use UsesCentralConnection;

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected $casts = ['created_at' => 'datetime', 'size' => 'integer'];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function path(): string
    {
        return storage_path('app/backups/'.$this->file);
    }
}
