<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;

class CentralSetting extends Model
{
    use UsesCentralConnection;

    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    protected $guarded = [];

    public static function read(string $key): ?string
    {
        return static::find($key)?->value;
    }

    public static function put(string $key, ?string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
