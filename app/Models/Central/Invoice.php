<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** What a clinic pays for the software: one price for the main branch and one for every dependent branch. */
class Invoice extends Model
{
    use UsesCentralConnection;

    public const STATUSES = ['unpaid' => 'Not paid', 'paid' => 'Paid', 'cancelled' => 'Cancelled'];

    protected $guarded = [];

    protected $casts = ['from_date' => 'date', 'to_date' => 'date', 'paid_on' => 'date', 'main_branch_price' => 'decimal:2', 'branch_price' => 'decimal:2', 'total' => 'decimal:2', 'lines' => 'array'];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
