<?php

namespace App\Http\Controllers;

use App\Application\Services\AttendanceService;
use App\Http\Requests\StoreAttendanceRequest;
use App\Models\Estudiante;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    private $attendanceService;

    public function __construct(AttendanceService $attendanceService)
    {
        $this->attendanceService = $attendanceService;
    }

    public function store(StoreAttendanceRequest $request)
    {
        $validated = $request->validated();

        $student = null;
        if (isset($validated['estudiante_id'])) {
            $student = Estudiante::find($validated['estudiante_id']);
        } elseif (isset($validated['codigo_sistema'])) {
            $student = Estudiante::where('codigo_sistema', $validated['codigo_sistema'])->first();
        }

        if ($student) {
            $this->authorizeAuxiliarForSection($request, $student->seccion_id);
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
        $this->authorizeAuxiliarForSection($request, $sectionId);

        return response()->json($this->attendanceService->getDailyAttendance($sectionId));
    }

    public function officiate(Request $request, $sectionId)
    {
        $this->authorizeAuxiliarForSection($request, $sectionId);

        try {
            $this->attendanceService->officiateSection($sectionId);
            return response()->json(['message' => 'Asistencia confirmada correctamente.']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
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
