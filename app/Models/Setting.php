<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One setting of the clinic, kept as key and value. */
class Setting extends Model
{
    /** What a new clinic starts with. Every value can be changed on the Settings page. */
    public const DEFAULTS = [
        'opening_time' => '08:00',             // the clinic opens
        'closing_time' => '17:00',             // the clinic closes
        'slot_minutes' => '15',                // the usual length of an appointment
        'currency' => 'AFN',                   // the money the clinic charges in
    ];

    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    protected $guarded = [];
}
