<?php

namespace App\Models\Central;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A reseller: a salesman or marketing agent who brings clinics, with a commission of his own.
 * With an email and a password he signs in to the panel of the resellers (guard "seller").
 */
class Seller extends Authenticatable
{
    use UsesCentralConnection;

    public const COMMISSIONS = ['percent' => 'Percentage of what the client pays', 'flat' => 'Flat amount for each sale'];

    protected $guarded = [];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = ['commission_value' => 'decimal:2', 'is_active' => 'boolean', 'password' => 'hashed', 'last_login_at' => 'datetime'];

    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function settlements(): HasMany
    {
        return $this->hasMany(SellerSettlement::class);
    }

    public function claims(): HasMany
    {
        return $this->hasMany(SellerClaim::class);
    }

    public function signups(): HasMany
    {
        return $this->hasMany(Signup::class);
    }

    /** May he sign in? He needs an email, a password, and to be active. */
    public function canSignIn(): bool
    {
        return $this->is_active && filled($this->email) && filled($this->password);
    }

    public function initials(): string
    {
        $words = preg_split('/\s+/u', trim($this->name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return mb_strtoupper(implode('', array_map(fn (string $word) => mb_substr($word, 0, 1), array_slice($words, 0, 2)))) ?: '?';
    }

    /** "10%" or "$50 a sale". */
    public function commissionLabel(): string
    {
        $value = rtrim(rtrim(number_format((float) $this->commission_value, 2), '0'), '.');

        return $this->commission_type === 'flat' ? '$'.$value.' '.__('a sale') : $value.'%';
    }
}
