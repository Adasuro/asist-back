<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Illuminate\Validation\Rules\Password;

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
    public function update(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'telefono' => 'nullable|string|max:20',
            'direccion' => 'nullable|string|max:255',
            'fecha_nacimiento' => 'nullable|date',
        ]);

        $user->update($request->only(['telefono', 'direccion', 'fecha_nacimiento']));

        return response()->json([
            'message' => 'Perfil actualizado correctamente.',
            'user' => $user,
        ]);
    }

    /**
     * Update profile photo.
     */
    public function updatePhoto(Request $request)
    {
        $request->validate([
            'foto' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        $user = $request->user();
        $file = $request->file('foto');

        try {
            $filename = 'profiles/' . $user->id . '_' . time() . '.webp';
            
            // Try to process image if GD is available
            if (extension_loaded('gd')) {
                $manager = new ImageManager(new Driver());
                $image = $manager->read($file->getRealPath());
                $image->cover(400, 400);
                $encoded = $image->toWebp(80);
                Storage::disk('public')->put($filename, $encoded);
            } else {
                // Fallback: just save the file (might not be webp if uploaded as something else)
                $path = $file->store('profiles', 'public');
                $filename = $path;
            }

            // Delete old photo if exists
            if ($user->foto_perfil) {
                Storage::disk('public')->delete($user->foto_perfil);
            }

            // Update user record
            $user->foto_perfil = $filename;
            $user->save();

            return response()->json([
                'message' => 'Foto de perfil actualizada.',
                'foto_url' => asset('storage/' . $filename),
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
     * Update password (Only for Superusers).
     */
    public function updatePassword(Request $request)
    {
        $user = $request->user();

        if ($user->rol !== 'superusuario') {
            return response()->json([
                'message' => 'Los auxiliares no pueden cambiar su propia contraseña. Contacte al administrador.',
            ], 403);
        }

        $request->validate([
            'current_password' => 'required|current_password',
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user->password = Hash::make($request->password);
        $user->save();

        return response()->json(['message' => 'Contraseña actualizada correctamente.']);
    }
}
