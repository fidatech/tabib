<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use App\Support\Num;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * What a patient owes for a visit. Its lines can change while nothing is paid on it; after the
 * first payment it is locked. A wrong invoice is voided (with a reason) once all is refunded.
 */
class Invoice extends Model
{
    use Audited;

    public const UNPAID = 'unpaid';

    public const PARTLY = 'partly';

    public const PAID = 'paid';

    public const VOID = 'void';

    public const STATUSES = [
        self::UNPAID => ['Unpaid', 'bg-danger'],
        self::PARTLY => ['Partly paid', 'bg-warning text-dark'],
        self::PAID => ['Paid', 'bg-success'],
        self::VOID => ['Void', 'bg-secondary'],
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['issued_on' => 'date', 'voided_at' => 'datetime', 'subtotal' => 'decimal:2', 'discount' => 'decimal:2', 'total' => 'decimal:2', 'paid' => 'decimal:2'];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class)->withTrashed();
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('position')->orderBy('id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy('received_at')->orderBy('id');
    }

    /** What is still to be paid. */
    public function balance(): string
    {
        return $this->status === self::VOID ? '0' : Num::sub($this->total, $this->paid);
    }

    public function isVoid(): bool
    {
        return $this->status === self::VOID;
    }

    /** Lines can change only while nothing was ever paid on it. */
    public function isEditable(): bool
    {
        return ! $this->isVoid() && ! $this->payments()->exists();
    }

    public function statusLabel(): string
    {
        return __(self::STATUSES[$this->status][0] ?? $this->status);
    }

    public function statusClass(): string
    {
        return self::STATUSES[$this->status][1] ?? 'bg-secondary';
    }

    public function auditLabel(): string
    {
        return $this->number;
    }
}
