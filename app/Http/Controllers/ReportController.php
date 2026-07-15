<?php

namespace App\Http\Controllers;

use App\Models\Asistencia;
use App\Models\Estudiante;
use App\Models\Seccion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Exports\AttendanceExport;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportController extends Controller
{
    private function getBaseQuery(Request $request)
    {
        $user = $request->user();
        $fechaInicio = $request->query('fecha_inicio');
        $fechaFin = $request->query('fecha_fin');
        $fecha = $request->query('fecha');
        $sectionId = $request->query('seccion_id');
        $estudianteNombre = $request->query('estudiante_nombre');

        $query = Asistencia::leftJoin('justificaciones', 'asistencias.id', '=', 'justificaciones.asistencia_id')
            ->join('estudiantes', 'asistencias.estudiante_id', '=', 'estudiantes.id')
            ->join('secciones', 'asistencias.seccion_id', '=', 'secciones.id')
            ->join('grados', 'secciones.grado_id', '=', 'grados.id');

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
            $seccionesIds = $user->secciones()->pluck('secciones.id');
            
            if ($sectionId) {
                if (!$seccionesIds->contains($sectionId)) {
                    abort(403, 'No tiene acceso a esta sección.');
                }
                $query->where('asistencias.seccion_id', $sectionId);
            } else {
                $query->whereIn('asistencias.seccion_id', $seccionesIds);
            }
        } elseif ($sectionId) {
            $query->where('asistencias.seccion_id', $sectionId);
        }

        return $query;
    }

    public function getAttendanceStats(Request $request)
    {
        $query = $this->getBaseQuery($request);

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

    public function getStudentPerformance(Request $request)
    {
        $user = $request->user();
        $sectionId = $request->query('seccion_id');
        $fechaInicio = $request->query('fecha_inicio');
        $fechaFin = $request->query('fecha_fin');

        if (!$sectionId) {
            return response()->json(['error' => 'Debe seleccionar una sección.'], 400);
        }

        // Seguridad: Validar acceso del auxiliar
        if ($user->rol === 'auxiliar') {
            $assigned = $user->secciones()->where('secciones.id', $sectionId)->exists();
            if (!$assigned) {
                return response()->json(['error' => 'No tiene acceso a esta sección.'], 403);
            }
        }

        // Obtener todos los alumnos de la sección
        $students = Estudiante::where('seccion_id', $sectionId)
            ->where('activo', true)
            ->select('id', 'nombre_completo', 'dni')
            ->orderBy('nombre_completo')
            ->get();

        $performance = $students->map(function ($student) use ($fechaInicio, $fechaFin) {
            $query = Asistencia::leftJoin('justificaciones', 'asistencias.id', '=', 'justificaciones.asistencia_id')
                ->where('asistencias.estudiante_id', $student->id);

            if ($fechaInicio && $fechaFin) {
                $query->whereBetween('asistencias.fecha', [$fechaInicio, $fechaFin]);
            }

            $stats = $query->select(
                DB::raw("COUNT(*) as total_dias"),
                DB::raw("SUM(CASE WHEN estado IN ('presente', 'tardanza') THEN 1 ELSE 0 END) as asistencias"),
                DB::raw("SUM(CASE WHEN estado = 'falta' THEN 1 ELSE 0 END) as faltas_totales"),
                DB::raw("SUM(CASE WHEN estado = 'tardanza' AND justificaciones.id IS NULL THEN 1 ELSE 0 END) as tardanzas_injustificadas"),
                DB::raw("SUM(CASE WHEN estado = 'falta' AND justificaciones.id IS NULL THEN 1 ELSE 0 END) as faltas_injustificadas"),
                DB::raw("SUM(CASE WHEN justificaciones.id IS NOT NULL THEN 1 ELSE 0 END) as total_justificados")
            )->first();

            return [
                'nombre_completo' => $student->nombre_completo,
                'dni' => $student->dni,
                'total_dias' => $stats->total_dias ?? 0,
                'asistencias' => $stats->asistencias ?? 0,
                'faltas_totales' => $stats->faltas_totales ?? 0,
                'faltas_injustificadas' => $stats->faltas_injustificadas ?? 0,
                'tardanzas_injustificadas' => $stats->tardanzas_injustificadas ?? 0,
                'total_justificados' => $stats->total_justificados ?? 0,
                'porcentaje_asistencia' => ($stats->total_dias > 0) 
                    ? round(($stats->asistencias / $stats->total_dias) * 100, 1) 
                    : 0
            ];
        });

        return response()->json($performance);
    }

    public function getRankings(Request $request)
    {
        $user = $request->user();
        $fechaInicio = $request->query('fecha_inicio');
        $fechaFin = $request->query('fecha_fin');

        $query = Asistencia::join('secciones', 'asistencias.seccion_id', '=', 'secciones.id')
            ->join('grados', 'secciones.grado_id', '=', 'grados.id');

        if ($fechaInicio && $fechaFin) {
            $query->whereBetween('asistencias.fecha', [$fechaInicio, $fechaFin]);
        } elseif ($fechaInicio) {
            $query->where('asistencias.fecha', '>=', $fechaInicio);
        } elseif ($fechaFin) {
            $query->where('asistencias.fecha', '<=', $fechaFin);
        } else {
            $query->whereMonth('asistencias.fecha', now()->month)
                  ->whereYear('asistencias.fecha', now()->year);
        }

        if ($user->rol === 'auxiliar') {
            $seccionesIds = $user->secciones()->pluck('secciones.id')->toArray();
            $query->whereIn('asistencias.seccion_id', $seccionesIds);
        }

        $sectionRankingsQuery = clone $query;
        $sectionRankings = $sectionRankingsQuery->select(
            'secciones.id',
            'secciones.nombre as seccion_nombre',
            'grados.nombre as grado_nombre',
            DB::raw("SUM(CASE WHEN asistencias.estado = 'falta' THEN 1 ELSE 0 END) as faltas"),
            DB::raw("SUM(CASE WHEN asistencias.estado = 'tardanza' THEN 1 ELSE 0 END) as tardanzas")
        )
        ->groupBy('secciones.id', 'secciones.nombre', 'grados.nombre')
        ->get();

        $gradeRankingsQuery = clone $query;
        $gradeRankings = $gradeRankingsQuery->select(
            'grados.id',
            'grados.nombre as grado_nombre',
            DB::raw("SUM(CASE WHEN asistencias.estado = 'falta' THEN 1 ELSE 0 END) as faltas"),
            DB::raw("SUM(CASE WHEN asistencias.estado = 'tardanza' THEN 1 ELSE 0 END) as tardanzas")
        )
        ->groupBy('grados.id', 'grados.nombre')
        ->get();

        return response()->json([
            'secciones' => $sectionRankings,
            'grados' => $gradeRankings
        ]);
    }

    public function exportExcel(Request $request)
    {
        $query = $this->getBaseQuery($request);
        
        $data = $query->select(
            'estudiantes.nombre_completo',
            'asistencias.fecha',
            'asistencias.estado',
            'justificaciones.id as justificacion_id',
            'secciones.nombre as seccion_nombre',
            'grados.nombre as grado_nombre'
        )
        ->orderBy('asistencias.fecha', 'desc')
        ->orderBy('estudiantes.nombre_completo', 'asc')
        ->get();

        return Excel::download(new AttendanceExport($data), 'Reporte_Asistencia_' . now()->format('Ymd_His') . '.xlsx');
    }

    public function exportPdf(Request $request)
    {
        $query = $this->getBaseQuery($request);
        
        $asistencias = $query->select(
            'estudiantes.nombre_completo',
            'asistencias.fecha',
            'asistencias.estado',
            'justificaciones.id as justificacion_id',
            'secciones.nombre as seccion_nombre',
            'grados.nombre as grado_nombre'
        )
        ->orderBy('asistencias.fecha', 'desc')
        ->orderBy('estudiantes.nombre_completo', 'asc')
        ->get();

        // Obtener estadísticas para el encabezado del PDF
        $stats = $this->getAttendanceStats($request)->getData(true);
        
        $seccionId = $request->query('seccion_id');
        $seccionNombre = null;
        if ($seccionId) {
            $record = Seccion::join('grados', 'secciones.grado_id', '=', 'grados.id')
                ->where('secciones.id', $seccionId)
                ->select('grados.nombre as grado_nombre', 'secciones.nombre as seccion_nombre')
                ->first();
            
            if ($record) {
                // Evitamos CONCAT de SQL para mantener compatibilidad con SQLite en local
                $seccionNombre = $record->grado_nombre . ' - ' . $record->seccion_nombre;
            }
        }

        $pdf = Pdf::loadView('reports.attendance', [
            'asistencias' => $asistencias,
            'stats' => $stats,
            'fechaInicio' => $request->query('fecha_inicio', now()->toDateString()),
            'fechaFin' => $request->query('fecha_fin', now()->toDateString()),
            'seccionNombre' => $seccionNombre,
            'userName' => $request->user()->nombre_completo
        ]);

        return $pdf->download('Reporte_Asistencia_' . now()->format('Ymd_His') . '.pdf');
    }
}
