<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function getCounts(Request $request)
    {
        $user = $request->user();

        if ($user->rol === 'superusuario') {
            return response()->json([
                'estudiantes' => DB::table('estudiantes')->count(),
                'secciones' => DB::table('secciones')->count(),
                'alertas' => DB::table('alertas')->where('resuelta', false)->count(),
            ]);
        }

        // Si es Auxiliar, filtrar por sus secciones
        $seccionesIds = DB::table('auxiliar_secciones')
            ->where('usuario_id', $user->id)
            ->pluck('seccion_id');

        return response()->json([
            'estudiantes' => DB::table('estudiantes')->whereIn('seccion_id', $seccionesIds)->count(),
            'secciones' => $seccionesIds->count(),
            'alertas' => 0, // Por ahora 0 hasta implementar alertas reales por sección
        ]);
    }

    public function getAdvancedStats(Request $request)
    {
        $user = $request->user();
        $seccionId = $request->query('seccion_id');
        $now = now();

        // 1. Determinar el ámbito de secciones
        if ($user->rol === 'auxiliar') {
            $assignedSecciones = DB::table('auxiliar_secciones')
                ->where('usuario_id', $user->id)
                ->pluck('seccion_id');
            
            if ($seccionId) {
                if (!$assignedSecciones->contains($seccionId)) {
                    return response()->json(['error' => 'No tiene acceso a esta sección.'], 403);
                }
                $seccionesIds = [$seccionId];
            } else {
                $seccionesIds = $assignedSecciones->toArray();
            }
        } else {
            // Superusuario puede ver todo o filtrar
            $seccionesIds = $seccionId ? [$seccionId] : DB::table('secciones')->pluck('id')->toArray();
        }

        if (empty($seccionesIds)) {
            return response()->json([
                'daily_percentage' => 0,
                'monthly_percentage' => 0,
                'critical_absences' => [],
                'critical_tardiness' => [],
                'trend' => []
            ]);
        }

        $totalStudents = DB::table('estudiantes')->whereIn('seccion_id', $seccionesIds)->where('activo', true)->count();

        // 2. Porcentaje de Asistencia Diaria (Hoy)
        $presentToday = DB::table('asistencias')
            ->whereIn('seccion_id', $seccionesIds)
            ->where('fecha', $now->toDateString())
            ->whereIn('estado', ['presente', 'tardanza'])
            ->count();
        
        $dailyPercentage = $totalStudents > 0 ? round(($presentToday / $totalStudents) * 100, 1) : 0;

        // 3. Porcentaje Mensual
        $daysInMonth = DB::table('asistencias')
            ->whereIn('seccion_id', $seccionesIds)
            ->whereMonth('fecha', $now->month)
            ->whereYear('fecha', $now->year)
            ->distinct('fecha')
            ->count('fecha');

        $totalPresentMonth = DB::table('asistencias')
            ->whereIn('seccion_id', $seccionesIds)
            ->whereMonth('fecha', $now->month)
            ->whereYear('fecha', $now->year)
            ->whereIn('estado', ['presente', 'tardanza'])
            ->count();

        $monthlyPercentage = ($totalStudents > 0 && $daysInMonth > 0) 
            ? round(($totalPresentMonth / ($totalStudents * $daysInMonth)) * 100, 1) 
            : 0;

        // 4. Alumnos Críticos - Faltas Injustificadas (Top 5 del año)
        $criticalAbsences = DB::table('asistencias')
            ->join('estudiantes', 'asistencias.estudiante_id', '=', 'estudiantes.id')
            ->leftJoin('justificaciones', 'asistencias.id', '=', 'justificaciones.asistencia_id')
            ->whereIn('asistencias.seccion_id', $seccionesIds)
            ->where('asistencias.estado', 'falta')
            ->whereNull('justificaciones.id')
            ->whereYear('asistencias.fecha', $now->year)
            ->select('estudiantes.nombre_completo', DB::raw('count(*) as total'))
            ->groupBy('estudiantes.id', 'estudiantes.nombre_completo')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        // 5. Alumnos Críticos - Tardanzas Injustificadas (Top 5 del mes)
        $criticalTardiness = DB::table('asistencias')
            ->join('estudiantes', 'asistencias.estudiante_id', '=', 'estudiantes.id')
            ->leftJoin('justificaciones', 'asistencias.id', '=', 'justificaciones.asistencia_id')
            ->whereIn('asistencias.seccion_id', $seccionesIds)
            ->where('asistencias.estado', 'tardanza')
            ->whereNull('justificaciones.id')
            ->whereMonth('asistencias.fecha', $now->month)
            ->whereYear('asistencias.fecha', $now->year)
            ->select('estudiantes.nombre_completo', DB::raw('count(*) as total'))
            ->groupBy('estudiantes.id', 'estudiantes.nombre_completo')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        // 6. Tendencia de los últimos 30 días
        $trend = DB::table('asistencias')
            ->whereIn('seccion_id', $seccionesIds)
            ->where('fecha', '>=', $now->subDays(30)->toDateString())
            ->select(
                'fecha',
                DB::raw("SUM(CASE WHEN estado = 'presente' THEN 1 ELSE 0 END) as presentes"),
                DB::raw("SUM(CASE WHEN estado = 'tardanza' THEN 1 ELSE 0 END) as tardanzas"),
                DB::raw("SUM(CASE WHEN estado = 'falta' THEN 1 ELSE 0 END) as faltas")
            )
            ->groupBy('fecha')
            ->orderBy('fecha')
            ->get();

        return response()->json([
            'daily_percentage' => $dailyPercentage,
            'monthly_percentage' => $monthlyPercentage,
            'critical_absences' => $criticalAbsences,
            'critical_tardiness' => $criticalTardiness,
            'trend' => $trend,
            'total_students' => $totalStudents
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
