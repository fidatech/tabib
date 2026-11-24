<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A request for the system made on the website, waiting for the administrator or decided. */
class Signup extends Model
{
    use UsesCentralConnection;

    public const STATUSES = ['pending' => 'Waiting', 'approved' => 'Approved', 'rejected' => 'Rejected'];

    /** The kinds of clinic asked for on the registration form. */
    public const TYPES = ['general' => 'General practice', 'dental' => 'Dental', 'gynecology' => 'Gynecology and obstetrics', 'pediatrics' => 'Pediatrics',
        'dermatology' => 'Dermatology', 'physiotherapy' => 'Physiotherapy', 'polyclinic' => 'Polyclinic (several specialties)', 'other' => 'Other'];

    protected $guarded = [];

    protected $hidden = ['password'];

    protected $casts = ['decided_at' => 'datetime', 'branches' => 'integer', 'doctors' => 'integer'];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'decided_by');
    }

    /** The reseller who asked for it, if one did. */
    public function seller(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /** The address the clinic asked for. */
    public function domain(): string
    {
        return $this->slug.'.'.config('tenancy.base_domain');
    }
}
