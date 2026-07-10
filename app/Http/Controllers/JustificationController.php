<?php

namespace App\Http\Controllers;

use App\Models\Asistencia;
use App\Models\Justificacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class JustificationController extends Controller
{
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

    public function store(Request $request)
    {
        $validated = $request->validate([
            'asistencia_id' => 'required|exists:asistencias,id',
            'motivo' => 'required|string',
            'documento_url' => 'nullable|string',
        ]);

        $asistencia = Asistencia::findOrFail($validated['asistencia_id']);
        
        if ($request->user()->rol === 'auxiliar') {
            $assignedSections = $request->user()->secciones()->pluck('secciones.id')->toArray();
            if (!in_array($asistencia->seccion_id, $assignedSections)) {
                return response()->json(['error' => 'No tiene permiso para justificar en esta sección.'], 403);
            }
        }

        $now = now();

        // 1. Validar plazo de 3 días hábiles para justificar
        $fechaAsistencia = \Carbon\Carbon::parse($asistencia->fecha);
        $diasHabilesTranscurridos = $fechaAsistencia->diffInDaysFiltered(function (\Carbon\Carbon $date) {
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

        $justification = Justificacion::updateOrCreate(
            ['asistencia_id' => $validated['asistencia_id']],
            [
                'registrado_por' => Auth::id(),
                'motivo' => $validated['motivo'],
                'documento_url' => $validated['documento_url'] ?? null,
                'fecha_presentacion' => $now->toDateString(),
            ]
        );

        // Actualizar alertas del estudiante al justificar
        $attendanceService = app(\App\Application\Services\AttendanceService::class);
        $attendanceService->checkAndGenerateAlerts($asistencia->estudiante_id);

        return response()->json([
            'message' => 'Justificación registrada correctamente.',
            'justification' => $justification
        ]);
    }

    public function show(Request $request, $asistenciaId)
    {
        $justification = Justificacion::with('asistencia')->where('asistencia_id', $asistenciaId)->first();
        
        if ($justification && $request->user()->rol === 'auxiliar') {
            $assignedSections = $request->user()->secciones()->pluck('secciones.id')->toArray();
            if (!in_array($justification->asistencia->seccion_id, $assignedSections)) {
                return response()->json(['error' => 'No tiene permiso.'], 403);
            }
        }

        return response()->json($justification);
    }
}
