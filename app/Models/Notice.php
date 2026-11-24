<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A short message for one user: "your transaction was rejected", "a transaction waits for you". */
class Notice extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected $casts = ['created_at' => 'datetime', 'read_at' => 'datetime'];

    public static function send(?int $userId, string $title, ?string $body = null, ?string $url = null): void
    {
        if ($userId) {
            static::create(['user_id' => $userId, 'title' => mb_substr($title, 0, 200), 'body' => $body ? mb_substr($body, 0, 500) : null, 'url' => $url]);
        }
    }
}
