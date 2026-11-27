<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmsMessage extends Model
{
    public const STATUSES = ['sent' => ['Sent', 'bg-success'], 'failed' => ['Failed', 'bg-danger'], 'logged' => ['Not sent (no gateway)', 'bg-secondary']];

    public const KINDS = ['reminder' => 'Reminder', 'booking' => 'Booking', 'test' => 'Test', 'code' => 'Booking code'];

    protected $guarded = [];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class)->withTrashed();
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }
}
