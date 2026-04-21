<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Grado;
use App\Models\AuxiliarSeccion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

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
    public function createAuxiliar(Request $request)
    {
        $request->validate([
            'nombre_completo' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:usuarios',
            'dni' => 'required|string|size:8|unique:usuarios',
            'password' => ['required', Password::defaults()],
            'grado_id' => 'required|uuid|exists:grados,id',
        ]);

        return DB::transaction(function () use ($request) {
            $user = User::create([
                'nombre_completo' => $request->nombre_completo,
                'email' => $request->email,
                'dni' => $request->dni,
                'password' => Hash::make($request->password),
                'rol' => 'auxiliar',
                'activo' => true,
            ]);

            // Assign to all sections of the grade
            $grado = Grado::with('secciones')->find($request->grado_id);
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
    public function updateAuxiliarPassword(Request $request, $id)
    {
        $request->validate([
            'password' => ['required', Password::defaults()],
        ]);

        $user = User::findOrFail($id);
        
        if ($user->rol !== 'auxiliar') {
            return response()->json(['message' => 'Solo se puede cambiar la contraseña de auxiliares.'], 400);
        }

        $user->password = Hash::make($request->password);
        $user->save();

        return response()->json(['message' => 'Contraseña actualizada correctamente.']);
    }

    /**
     * List all grades.
     */
    public function listGrados()
    {
        return response()->json(Grado::all());
    }
}
