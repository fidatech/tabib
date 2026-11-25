<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;

/** A medicine of the platform list (central), copied into every clinic. */
class DrugListEntry extends Model
{
    use UsesCentralConnection;

    protected $table = 'drug_list';

    protected $guarded = [];

    protected $casts = ['controlled' => 'boolean', 'is_active' => 'boolean', 'mg_per_unit' => 'float'];

    public function label(): string
    {
        return trim($this->name.' '.$this->strength.' '.$this->form);
    }

    public function scopeSearch($query, string $term)
    {
        foreach (preg_split('/\s+/u', trim($term), -1, PREG_SPLIT_NO_EMPTY) as $word) {
            $query->where(fn ($q) => $q->where('name', 'like', "%$word%")->orWhere('brands', 'like', "%$word%")->orWhere('strength', 'like', "%$word%")
                ->orWhere('code', 'like', "%$word%")->orWhere('class', 'like', "%$word%"));
        }

        return $query;
    }
}
