<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\ProfileImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UploadController extends Controller
{
    /** Data URL me base64 payload ka upper bound (chars me). */
    private const MAX_BASE64_CHARS = 10485760;

    /** Decoded binary ka upper bound. */
    private const MAX_BYTES = 3 * 1024 * 1024;

    /** Detected MIME => extension jo hum file ko denge. */
    private const EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    public function uploadImage(Request $request)
    {
        $validated = $request->validate([
            'image' => ['required', 'string', 'max:'.self::MAX_BASE64_CHARS],
        ]);

        $data = $validated['image'];

        if (! str_starts_with($data, 'data:image/')) {
            return response()->json(['message' => 'Invalid image data.'], 422);
        }

        [, $base64] = array_pad(explode(',', $data, 2), 2, null);

        if ($base64 === null) {
            return response()->json(['message' => 'Invalid image data.'], 422);
        }

        $decoded = base64_decode($base64, true);

        if ($decoded === false || $decoded === '') {
            return response()->json(['message' => 'Invalid image data.'], 422);
        }

        if (strlen($decoded) > self::MAX_BYTES) {
            return response()->json(['message' => 'Image is too large. Please pick a smaller one.'], 422);
        }

        // `data:` URL ka type claim (aur SVG `<script>` wali file) yahan aata
        // hai — asli bytes se type pakdo. Warna `.php` jaisa payload `.png`
        // naam se save ho jayega.
        $detected = @getimagesizefromstring($decoded);
        $mime = $detected['mime'] ?? null;

        if ($mime === null || ! isset(self::EXTENSIONS[$mime])) {
            return response()->json([
                'message' => 'That file is not a valid image. Use JPG, PNG, GIF or WEBP.',
            ], 422);
        }

        $filename = Str::uuid().'.'.self::EXTENSIONS[$mime];
        $relative = ProfileImage::DIRECTORY.'/'.$filename;

        Storage::disk('public')->put($relative, $decoded);

        return response()->json([
            'message' => 'Image uploaded successfully',
            'url' => Storage::disk('public')->url($relative),
        ]);
    }
}
