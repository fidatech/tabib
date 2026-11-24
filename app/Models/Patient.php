<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/** A patient of the clinic. Removed patients are only hidden: their history stays. */
class Patient extends Model
{
    use Audited, SoftDeletes;

    public const GENDERS = ['male' => 'Male', 'female' => 'Female', 'other' => 'Other'];

    public const BLOOD_GROUPS = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['date_of_birth' => 'date', 'dob_estimated' => 'boolean', 'no_sms' => 'boolean'];
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Age in years today, or null when the date of birth is not known. */
    public function age(): ?int
    {
        return $this->date_of_birth ? (int) $this->date_of_birth->diffInYears(Carbon::parse(today_iso())) : null;
    }

    /** "35 y" or, for a baby, "8 m". */
    public function ageText(): string
    {
        if (! $this->date_of_birth) {
            return '';
        }
        $years = $this->age();
        if ($years < 2) {
            return __(':count m', ['count' => (int) $this->date_of_birth->diffInMonths(Carbon::parse(today_iso()))]);
        }

        return __(':count y', ['count' => $years]).($this->dob_estimated ? ' ~' : '');
    }

    /** The patient said at registration that he has no allergy. */
    public function knowsNoAllergies(): bool
    {
        return in_array(mb_strtolower(trim((string) $this->allergies), 'UTF-8'), ['none', 'none known', 'no', 'nkda', 'nka', '-', 'ندارد', 'نشته', 'هیچ'], true);
    }

    public function auditLabel(): string
    {
        return $this->number.' '.$this->name;
    }

    /** Search by name, father's name, phone, record number or identity card: the words must all match. */
    public function scopeSearch($query, string $term)
    {
        $term = trim($term);
        if ($term === '') {
            return $query;
        }
        // a record number or a phone is looked up as it is typed
        foreach (preg_split('/\s+/u', $term) as $word) {
            $query->where(function ($q) use ($word) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $word).'%';
                $q->where('name', 'like', $like)->orWhere('father_name', 'like', $like)->orWhere('number', 'like', $like)
                    ->orWhere('phone', 'like', $like)->orWhere('phone2', 'like', $like)->orWhere('tazkira', 'like', $like);
            });
        }

        return $query;
    }
}
