<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A place the clinic works from.
 *   main        holds the licence; there is exactly one
 *   dependent   another place of the same clinic, with its own staff
 */
class Branch extends Model
{
    use Audited;

    public const MAIN = 'main';

    public const DEPENDENT = 'dependent';

    public const TYPES = [self::MAIN => 'Main branch', self::DEPENDENT => 'Branch'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** The branch that holds the licence. */
    public static function main(): self
    {
        return static::where('type', self::MAIN)->firstOrFail();
    }

    public function isMain(): bool
    {
        return $this->type === self::MAIN;
    }

    /** Branches whose people sign in: all of them. */
    public function scopeWithBooks($query)
    {
        return $query->whereIn('type', [self::MAIN, self::DEPENDENT]);
    }
}
