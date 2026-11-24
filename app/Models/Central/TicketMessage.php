<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;

class TicketMessage extends Model
{
    use UsesCentralConnection;

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected $casts = ['created_at' => 'datetime'];
}
