<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function getCounts()
    {
        return response()->json([
            'estudiantes' => DB::table('estudiantes')->count(),
            'secciones' => DB::table('secciones')->count(),
            'alertas' => DB::table('alertas')->where('resuelta', false)->count(),
        ]);
    }

    public function getAssignedSections(Request $request)
    {
        $user = $request->user();

        $sections = DB::table('auxiliar_secciones')
            ->join('secciones', 'auxiliar_secciones.seccion_id', '=', 'secciones.id')
            ->join('grados', 'secciones.grado_id', '=', 'grados.id')
            ->where('auxiliar_secciones.usuario_id', $user->id)
            ->where('auxiliar_secciones.activo', true)
            ->select(
                'secciones.id',
                'secciones.nombre',
                'grados.nombre as grado_nombre',
                'grados.nivel as grado_nivel'
            )
            ->get();

        return response()->json($sections->map(function ($sec) {
            return [
                'id' => $sec->id,
                'nombre' => $sec->nombre,
                'grado' => [
                    'nombre' => $sec->grado_nombre,
                    'nivel' => $sec->grado_nivel,
                ]
            ];
        }));
    }
}
