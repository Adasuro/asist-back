<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfileRequest;
use App\Http\Requests\UpdatePhotoRequest;
use App\Http\Requests\UpdatePasswordRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class ProfileController extends Controller
{
    /**
     * Get the authenticated user's profile.
     */
    public function show(Request $request)
    {
        return response()->json($request->user());
    }

    /**
     * Update basic profile information.
     */
    public function update(UpdateProfileRequest $request)
    {
        $user = $request->user();
        $user->update($request->validated());

        return response()->json([
            'message' => 'Perfil actualizado correctamente.',
            'user' => $user,
        ]);
    }

    /**
     * Update profile photo.
     */
    public function updatePhoto(UpdatePhotoRequest $request)
    {
        $user = $request->user();
        $file = $request->file('foto');
        $disk = 'public';

        try {
            $filename = 'profiles/' . $user->id . '_' . time() . '.webp';
            
            // Try to process image if GD is available
            if (extension_loaded('gd')) {
                $manager = new ImageManager(new Driver());
                $image = $manager->read($file->getRealPath());
                $image->cover(400, 400);
                $encoded = $image->toWebp(80);
                Storage::disk($disk)->put($filename, $encoded);
            } else {
                // Fallback: just save the file
                $path = $file->store('profiles', $disk);
                $filename = $path;
            }

            // Delete old photo if exists
            if ($user->foto_perfil) {
                Storage::disk($disk)->delete($user->foto_perfil);
            }

            // Update user record
            $user->foto_perfil = $filename;
            $user->save();

            return response()->json([
                'message' => 'Foto de perfil actualizada.',
                'foto_url' => Storage::disk($disk)->url($filename),
                'user' => $user,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al procesar la imagen.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update password (Only for Authorized Users).
     */
    public function updatePassword(UpdatePasswordRequest $request)
    {
        $user = $request->user();
        $user->password = Hash::make($request->password);
        $user->save();

        return response()->json(['message' => 'Contraseña actualizada correctamente.']);
    }
}
