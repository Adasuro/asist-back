<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateAuxiliarRequest;
use App\Http\Requests\UpdateAuxiliarRequest;
use App\Http\Requests\UpdateAuxiliarPasswordRequest;
use App\Models\User;
use App\Models\Grado;
use App\Models\AuxiliarSeccion;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class SuperUserController extends Controller
{
    /**
     * List all auxiliaries.
     */
    public function listAuxiliaries()
    {
        $auxiliaries = User::where('rol', 'auxiliar')
            ->with(['secciones.grado'])
            ->get();

        return response()->json($auxiliaries);
    }

    /**
     * Create a new auxiliary account and assign to a grade.
     */
    public function createAuxiliar(CreateAuxiliarRequest $request)
    {
        $validated = $request->validated();

        return DB::transaction(function () use ($validated) {
            $user = User::create([
                'nombre_completo' => $validated['nombre_completo'],
                'email' => $validated['email'],
                'dni' => $validated['dni'],
                'password' => Hash::make($validated['password']),
                'rol' => 'auxiliar',
                'activo' => true,
            ]);

            // Assign to all sections of the grade
            $grado = Grado::with('secciones')->find($validated['grado_id']);
            foreach ($grado->secciones as $seccion) {
                AuxiliarSeccion::create([
                    'usuario_id' => $user->id,
                    'seccion_id' => $seccion->id,
                    'activo' => true,
                ]);
            }

            return response()->json([
                'message' => 'Auxiliar creado correctamente.',
                'user' => $user->load('secciones.grado'),
            ], 201);
        });
    }

    /**
     * Update an existing auxiliary.
     */
    public function updateAuxiliar(UpdateAuxiliarRequest $request, $id)
    {
        $user = User::findOrFail($id);

        if ($user->rol !== 'auxiliar') {
            return response()->json(['message' => 'Solo se puede editar auxiliares.'], 400);
        }

        $validated = $request->validated();

        return DB::transaction(function () use ($validated, $user) {
            $user->update([
                'nombre_completo' => $validated['nombre_completo'],
                'email' => $validated['email'],
                'dni' => $validated['dni'],
            ]);

            // Actualizar asignación de grado si ha cambiado
            $currentGradoId = $user->secciones()->first()?->grado_id;

            if ($currentGradoId !== $validated['grado_id']) {
                AuxiliarSeccion::where('usuario_id', $user->id)->delete();
                $grado = Grado::with('secciones')->find($validated['grado_id']);
                foreach ($grado->secciones as $seccion) {
                    AuxiliarSeccion::create([
                        'usuario_id' => $user->id,
                        'seccion_id' => $seccion->id,
                        'activo' => true,
                    ]);
                }
            }

            return response()->json([
                'message' => 'Auxiliar actualizado correctamente.',
                'user' => $user->load('secciones.grado'),
            ]);
        });
    }

    /**
     * Activate or deactivate an auxiliary.
     */
    public function toggleAuxiliarStatus($id)
    {
        $user = User::findOrFail($id);
        
        if ($user->rol !== 'auxiliar') {
            return response()->json(['message' => 'Solo se puede cambiar el estado de auxiliares.'], 400);
        }

        $user->activo = !$user->activo;
        $user->save();

        return response()->json([
            'message' => $user->activo ? 'Auxiliar activado.' : 'Auxiliar desactivado.',
            'user' => $user,
        ]);
    }

    /**
     * Change auxiliary password.
     */
    public function updateAuxiliarPassword(UpdateAuxiliarPasswordRequest $request, $id)
    {
        $user = User::findOrFail($id);
        
        if ($user->rol !== 'auxiliar') {
            return response()->json(['message' => 'Solo se puede cambiar la contraseña de auxiliares.'], 400);
        }

        $validated = $request->validated();

        $user->password = Hash::make($validated['password']);
        $user->save();

        return response()->json(['message' => 'Contraseña actualizada correctamente.']);
    }

    /**
     * List all grades.
     */
    public function listGrados()
    {
        return response()->json(Grado::with('secciones')->get());
    }
}
