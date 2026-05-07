<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function getAttendanceStats(Request $request)
    {
        $user = $request->user();
        $fechaInicio = $request->query('fecha_inicio');
        $fechaFin = $request->query('fecha_fin');
        $fecha = $request->query('fecha');
        $sectionId = $request->query('seccion_id');
        $estudianteNombre = $request->query('estudiante_nombre');

        $query = DB::table('asistencias')
            ->leftJoin('justificaciones', 'asistencias.id', '=', 'justificaciones.asistencia_id')
            ->join('estudiantes', 'asistencias.estudiante_id', '=', 'estudiantes.id');

        // Filtro de fecha o rango
        if ($fechaInicio && $fechaFin) {
            $query->whereBetween('asistencias.fecha', [$fechaInicio, $fechaFin]);
        } elseif ($fechaInicio) {
            $query->where('asistencias.fecha', '>=', $fechaInicio);
        } elseif ($fechaFin) {
            $query->where('asistencias.fecha', '<=', $fechaFin);
        } elseif ($fecha) {
            $query->where('asistencias.fecha', $fecha);
        } else {
            $query->where('asistencias.fecha', now()->toDateString());
        }

        // Filtro de nombre de estudiante
        if ($estudianteNombre) {
            $query->where('estudiantes.nombre_completo', 'LIKE', "%{$estudianteNombre}%");
        }

        // Filtro de sección y seguridad por rol
        if ($user->rol === 'auxiliar') {
            $seccionesIds = DB::table('auxiliar_secciones')
                ->where('usuario_id', $user->id)
                ->pluck('seccion_id');
            
            if ($sectionId) {
                if (!$seccionesIds->contains($sectionId)) {
                    return response()->json(['error' => 'No tiene acceso a esta sección.'], 403);
                }
                $query->where('asistencias.seccion_id', $sectionId);
            } else {
                $query->whereIn('asistencias.seccion_id', $seccionesIds);
            }
        } elseif ($sectionId) {
            $query->where('asistencias.seccion_id', $sectionId);
        }

        $stats = $query->select(
            'asistencias.estado',
            DB::raw('count(*) as total'),
            DB::raw('count(justificaciones.id) as justificados')
        )
        ->groupBy('asistencias.estado')
        ->get();

        $result = [
            'presente' => 0,
            'tardanza_justificada' => 0,
            'tardanza_injustificada' => 0,
            'falta_justificada' => 0,
            'falta_injustificada' => 0,
            'total' => 0
        ];

        foreach ($stats as $stat) {
            if ($stat->estado === 'presente') {
                $result['presente'] = $stat->total;
            } elseif ($stat->estado === 'tardanza') {
                $result['tardanza_justificada'] = $stat->justificados;
                $result['tardanza_injustificada'] = $stat->total - $stat->justificados;
            } elseif ($stat->estado === 'falta') {
                $result['falta_justificada'] = $stat->justificados;
                $result['falta_injustificada'] = $stat->total - $stat->justificados;
            }
            $result['total'] += $stat->total;
        }

        return response()->json($result);
    }
}
