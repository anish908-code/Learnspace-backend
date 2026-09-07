<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UploadController extends Controller
{
    public function uploadImage(Request $request)
    {
        $validated = $request->validate([
            'image' => ['required', 'string', 'max:10485760'],
        ]);

        $data = $validated['image'];

        if (! str_starts_with($data, 'data:image/')) {
            return response()->json(['message' => 'Invalid image data.'], 422);
        }

        [$meta, $base64] = explode(',', $data, 2);

        if (! preg_match('#^data:image/(\w+);base64$#', $meta, $matches)) {
            return response()->json(['message' => 'Unsupported image format.'], 422);
        }

        $extension = strtolower($matches[1]);
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        if (! in_array($extension, $allowed, true)) {
            return response()->json(['message' => 'Unsupported image format.'], 422);
        }

        $decoded = base64_decode($base64, true);

        if ($decoded === false) {
            return response()->json(['message' => 'Invalid image data.'], 422);
        }

        $filename = Str::uuid().'.'.$extension;

        Storage::disk('public')->put('avatars/'.$filename, $decoded);

        return response()->json([
            'message' => 'Image uploaded successfully',
            'url' => Storage::disk('public')->url('avatars/'.$filename),
        ]);
    }
}