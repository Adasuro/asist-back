<?php

namespace App\Http\Controllers;

use App\Application\Services\AttendanceService;
use App\Http\Requests\StoreJustificationRequest;
use App\Models\Asistencia;
use App\Models\Justificacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class JustificationController extends Controller
{
    public function __construct(
        protected AttendanceService $attendanceService
    ) {}

    public function index(Request $request)
    {
        $query = Justificacion::with(['asistencia.estudiante.seccion.grado', 'registradoPor'])
            ->orderBy('created_at', 'desc');

        if ($request->user()->rol === 'auxiliar') {
            $assignedSections = $request->user()->secciones()->pluck('secciones.id')->toArray();
            $query->whereHas('asistencia.estudiante', function ($q) use ($assignedSections) {
                $q->whereIn('seccion_id', $assignedSections);
            });
        }

        $justifications = $query->get();
            
        return response()->json($justifications);
    }

    public function store(StoreJustificationRequest $request)
    {
        $validated = $request->validated();
        $asistencia = Asistencia::findOrFail($validated['asistencia_id']);
        
        $this->authorizeAuxiliarForSection($request, $asistencia->seccion_id);

        $now = now();

        // 1. Validar plazo de 3 días hábiles para justificar
        $fechaAsistencia = Carbon::parse($asistencia->fecha);
        $diasHabilesTranscurridos = $fechaAsistencia->diffInDaysFiltered(function (Carbon $date) {
            return !$date->isWeekend();
        }, $now);

        // Si ya existe una justificación, es una edición
        $existingJustification = Justificacion::where('asistencia_id', $validated['asistencia_id'])->first();

        if ($existingJustification) {
            // 2. Bloqueo de edición de 24 horas (Criterio Justificación #6)
            if ($existingJustification->created_at->diffInHours($now) > 24) {
                return response()->json([
                    'error' => 'El plazo de 24 horas para editar esta justificación ha expirado.'
                ], 403);
            }
        } else {
            // Es un registro nuevo: Validar los 3 días hábiles
            if ($diasHabilesTranscurridos > 3) {
                return response()->json([
                    'error' => 'El plazo de 3 días hábiles para justificar esta inasistencia/tardanza ha expirado.'
                ], 403);
            }
        }

        // Subida de archivos (documento de sustento)
        $documentoUrl = $existingJustification ? $existingJustification->documento_url : null;
        if ($request->hasFile('documento')) {
            $file = $request->file('documento');
            $filename = time() . '_' . $file->getClientOriginalName();
            
            // Usar storage local
            $path = $file->storeAs('justificaciones', $filename, 'public');
            $documentoUrl = asset('storage/' . $path);
        } else if (isset($validated['documento_url'])) {
            $documentoUrl = $validated['documento_url'];
        }

        $justification = Justificacion::updateOrCreate(
            ['asistencia_id' => $validated['asistencia_id']],
            [
                'registrado_por' => Auth::id(),
                'motivo' => $validated['motivo'],
                'documento_url' => $documentoUrl,
                'fecha_presentacion' => $now->toDateString(),
            ]
        );

        // Actualizar alertas del estudiante al justificar
        $this->attendanceService->checkAndGenerateAlerts($asistencia->estudiante_id);

        return response()->json([
            'message' => 'Justificación registrada correctamente.',
            'justification' => $justification
        ]);
    }

    public function show(Request $request, $asistenciaId)
    {
        $justification = Justificacion::with('asistencia')->where('asistencia_id', $asistenciaId)->first();
        
        if ($justification) {
            $this->authorizeAuxiliarForSection($request, $justification->asistencia->seccion_id);
        }

        return response()->json($justification);
    }

    /**
     * Authorize auxiliary user access for a specific academic section.
     */
    private function authorizeAuxiliarForSection(Request $request, $sectionId): void
    {
        if ($request->user()->rol === 'auxiliar') {
            $assignedSections = $request->user()->secciones()->pluck('secciones.id')->toArray();
            if (!in_array($sectionId, $assignedSections)) {
                abort(response()->json(['error' => 'No tiene permiso para acceder a esta sección.'], 403));
            }
        }
    }
}
