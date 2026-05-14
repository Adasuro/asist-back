<?php

namespace App\Http\Controllers;

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

        $query = DB::table('asistencias')
            ->leftJoin('justificaciones', 'asistencias.id', '=', 'justificaciones.asistencia_id')
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
            $seccionesIds = DB::table('auxiliar_secciones')
                ->where('usuario_id', $user->id)
                ->pluck('seccion_id');
            
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
            $seccionNombre = DB::table('secciones')
                ->join('grados', 'secciones.grado_id', '=', 'grados.id')
                ->where('secciones.id', $seccionId)
                ->select(DB::raw("CONCAT(grados.nombre, ' - ', secciones.nombre) as full_name"))
                ->first()?->full_name;
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
