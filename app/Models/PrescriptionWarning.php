<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A warning the doctor went past when signing, with his reason. */
class PrescriptionWarning extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected $casts = ['created_at' => 'datetime'];
}
