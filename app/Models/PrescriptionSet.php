<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A prescription a doctor writes often, kept under a name to add in one click. */
class PrescriptionSet extends Model
{
    protected $guarded = [];

    protected $casts = ['items' => 'array'];
}
