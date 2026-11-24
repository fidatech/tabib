<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Identity cards, photos and fingerprints. Every file is encrypted before it touches
 * the disk, and every clinic has a folder of its own that no other clinic can reach.
 */
class Vault
{
    /** Keep an uploaded file; returns the path to remember. */
    public static function put(UploadedFile $file, string $folder): string
    {
        return self::putContents((string) file_get_contents($file->getRealPath()), $folder);
    }

    public static function putContents(string $contents, string $folder): string
    {
        $path = 'vault/'.(tenant()?->getKey() ?? 'central').'/'.trim($folder, '/').'/'.Str::random(40).'.enc';
        Storage::disk('local')->put($path, Crypt::encryptString(base64_encode($contents)));

        return $path;
    }

    /** The file as it was uploaded, or null when it is gone. */
    public static function get(string $path): ?string
    {
        // a path of another clinic is never read
        if (! str_starts_with($path, 'vault/'.(tenant()?->getKey() ?? 'central').'/') || ! Storage::disk('local')->exists($path)) {
            return null;
        }

        return base64_decode(Crypt::decryptString(Storage::disk('local')->get($path)));
    }

    public static function delete(string $path): void
    {
        if (str_starts_with($path, 'vault/'.(tenant()?->getKey() ?? 'central').'/')) {
            Storage::disk('local')->delete($path);
        }
    }
}
