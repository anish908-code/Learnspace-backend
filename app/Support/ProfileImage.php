<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| ProfileImage
|--------------------------------------------------------------------------
|
| Avatar URL ko users aur students dono tables me rakhna padta hai — admin
| ka student list `student.user.profile_image` padhta hai, header avatar
| `student.profile_image` padhta hai. Ek jagah likha do dusri jagah stale
| reh jaati hai, isliye sync aur purani file ki cleanup ek hi jagah hai.
|
*/

class ProfileImage
{
    public const DIRECTORY = 'avatars';

    /**
     * Naya photo set karo, users row ko mirror karo, aur badli hui purani
     * photo ka file delete kar do (garbage na bache).
     */
    public static function replace(User $user, ?string $previousUrl, ?string $newUrl): void
    {
        if ($newUrl !== $user->profile_image) {
            $user->forceFill(['profile_image' => $newUrl])->save();
        }

        // Sirf purani file delete karo — nayi to abhi use ho rahi hai. Aur agar
        // URL badla hi nahi (double submit / form resubmit) to kuch mat karo,
        // warna live avatar delete ho jayegi.
        if ($previousUrl !== null && $previousUrl !== $newUrl) {
            self::delete($previousUrl);
        }
    }

    /**
     * Sirf tab delete karo jab path hamare avatars directory ke andar ho —
     * koi URL dekar doosri files delete na ho jayein.
     */
    public static function delete(?string ...$urls): void
    {
        $paths = [];

        foreach ($urls as $url) {
            if (! is_string($url) || $url === '') {
                continue;
            }

            $path = self::toRelativePath($url);

            if ($path !== null) {
                $paths[] = $path;
            }
        }

        if ($paths !== []) {
            Storage::disk('public')->delete(array_values(array_unique($paths)));
        }
    }

    /**
     * `{APP_URL}/storage/avatars/x.jpg` ya `avatars/x.jpg` ko disk-relative
     * path me badlo. `avatars/` prefix ke bahar ho to null (delete mat karo).
     */
    private static function toRelativePath(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH) ?: $url;
        $path = ltrim(rawurldecode($path), '/');

        // `{APP_URL}/storage/avatars/x.jpg` ka sirf `avatars/x.jpg` hissa chahiye.
        $prefix = self::DIRECTORY.'/';
        $index = strpos($path, 'storage/'.$prefix);

        if ($index !== false) {
            $path = substr($path, $index + strlen('storage/'));
        }

        if (! str_starts_with($path, $prefix)) {
            return null;
        }

        // `..` se disk ke bahar nikalne se roko.
        if (in_array('..', explode('/', $path), true)) {
            return null;
        }

        return $path;
    }
}
