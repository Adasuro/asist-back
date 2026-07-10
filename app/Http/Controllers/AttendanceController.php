<?php

namespace App\Http\Controllers;

use App\Application\Services\AttendanceService;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    private $attendanceService;

    public function __construct(AttendanceService $attendanceService)
    {
        $this->attendanceService = $attendanceService;
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'estudiante_id' => 'required_without:codigo_sistema|exists:estudiantes,id',
            'codigo_sistema' => 'required_without:estudiante_id|string',
            'estado' => 'nullable|in:presente,tardanza,falta',
            'metodo_registro' => 'required|in:codigo,manual',
            'observacion' => 'nullable|string',
        ]);

        $student = null;
        if (isset($validated['estudiante_id'])) {
            $student = \App\Models\Estudiante::find($validated['estudiante_id']);
        } elseif (isset($validated['codigo_sistema'])) {
            $student = \App\Models\Estudiante::where('codigo_sistema', $validated['codigo_sistema'])->first();
        }

        if ($student && $request->user()->rol === 'auxiliar') {
            $assignedSections = $request->user()->secciones()->pluck('secciones.id')->toArray();
            if (!in_array($student->seccion_id, $assignedSections)) {
                return response()->json(['error' => 'No tiene permiso para registrar asistencia a este estudiante.'], 403);
            }
        }

        try {
            $attendance = $this->attendanceService->registerAttendance($validated);
            return response()->json([
                'message' => 'Asistencia registrada correctamente.',
                'attendance' => $attendance->load('estudiante')
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function sectionDaily(Request $request, $sectionId)
    {
        if ($request->user()->rol === 'auxiliar') {
            $assignedSections = $request->user()->secciones()->pluck('secciones.id')->toArray();
            if (!in_array($sectionId, $assignedSections)) {
                return response()->json(['error' => 'No tiene permiso para ver esta sección.'], 403);
            }
        }
        return response()->json($this->attendanceService->getDailyAttendance($sectionId));
    }

    public function officiate(Request $request, $sectionId)
    {
        if ($request->user()->rol === 'auxiliar') {
            $assignedSections = $request->user()->secciones()->pluck('secciones.id')->toArray();
            if (!in_array($sectionId, $assignedSections)) {
                return response()->json(['error' => 'No tiene permiso para oficializar esta sección.'], 403);
            }
        }
        try {
            $this->attendanceService->officiateSection($sectionId);
            return response()->json(['message' => 'Asistencia confirmada correctamente.']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
