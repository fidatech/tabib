<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;

/** A diagnosis code of ICD-10-CM, shared by every clinic (php artisan icd10:import). */
class Icd10Code extends Model
{
    use UsesCentralConnection;

    protected $table = 'icd10_codes';

    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = ['billable' => 'boolean'];

    /** By code ("J06", "e11.9") or by words of the description ("urinary infection"). */
    public static function lookup(string $term, int $limit = 15): \Illuminate\Support\Collection
    {
        $term = trim($term);
        if (mb_strlen($term) < 2) {
            return collect();
        }
        $query = static::query();
        if (preg_match('/^[A-Za-z]\d[\dA-Za-z.]*$/', $term)) {
            $query->where('code', 'like', strtoupper($term).'%')->orderBy('code');
        } else {
            foreach (preg_split('/\s+/u', $term) as $word) {
                $query->where(fn ($q) => $q->where('description', 'like', '%'.$word.'%')->orWhere('description_local', 'like', '%'.$word.'%'));
            }
            // whole codes before headings, short descriptions (the usual ones) first
            $query->orderByDesc('billable')->orderByRaw('length(description)');
        }

        return $query->limit($limit)->get();
    }
}
