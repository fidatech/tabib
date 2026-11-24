<?php

namespace App\Models\Concerns;

use App\Models\HistoryRevision;

/**
 * Every change of an entry of the history is kept: who, when, the old and the new value,
 * and the reason given. A model using this has a patient_id.
 */
trait HasRevisions
{
    /** The reason the next save is made for ("entered in error: wrong patient"). Cleared after it is written. */
    public ?string $revisionReason = null;

    public static function bootHasRevisions(): void
    {
        static::created(function ($model) {
            $model->writeRevision('created', collect($model->getAttributes())->except(['id', 'created_at', 'updated_at', 'created_by', 'updated_by', 'patient_id'])->filter(fn ($v) => $v !== null && $v !== '')->all());
        });
        static::updated(function ($model) {
            $changes = [];
            foreach ($model->getChanges() as $field => $new) {
                if (in_array($field, ['updated_at', 'updated_by'], true)) {
                    continue;
                }
                $changes[$field] = ['from' => $model->getOriginal($field), 'to' => $new];
            }
            if ($changes) {
                $action = isset($changes['status']) && $changes['status']['to'] === 'entered_in_error' ? 'entered_in_error'
                    : (isset($changes['status']) ? 'status' : 'updated');
                $model->writeRevision($action, $changes);
            }
        });
    }

    public function revisions()
    {
        return $this->hasMany(HistoryRevision::class, 'subject_id')->where('subject_type', class_basename($this))->orderByDesc('id');
    }

    protected function writeRevision(string $action, array $changes): void
    {
        HistoryRevision::create([
            'patient_id' => $this->patient_id, 'subject_type' => class_basename($this), 'subject_id' => $this->getKey(),
            'action' => $action, 'changes' => $changes ?: null, 'reason' => $this->revisionReason, 'user_id' => auth()->id(),
        ]);
        $this->revisionReason = null;
    }
}
