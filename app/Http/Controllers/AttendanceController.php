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

    public function bulkStore(Request $request)
    {
        $request->validate([
            'seccion_id' => 'required|exists:secciones,id',
            'students' => 'required|array',
            'students.*.estudiante_id' => 'required|exists:estudiantes,id',
            'students.*.estado' => 'required|in:presente,falta',
        ]);

        $this->authorizeAuxiliarForSection($request, $request->input('seccion_id'));

        try {
            $this->attendanceService->registerBulkAttendance($request->all());
            return response()->json([
                'message' => 'Asistencia masiva registrada y oficializada correctamente.'
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

    public function unjustified(Request $request)
    {
        $query = \App\Models\Asistencia::with(['estudiante.seccion.grado'])
            ->whereIn('estado', ['tardanza', 'falta'])
            ->whereDoesntHave('justificacion')
            ->orderBy('fecha', 'desc');

        if ($request->user()->rol === 'auxiliar') {
            $assignedSections = $request->user()->secciones()->pluck('secciones.id')->toArray();
            $query->whereHas('estudiante', function ($q) use ($assignedSections) {
                $q->whereIn('seccion_id', $assignedSections);
            });
        }

        if ($request->has('estudiante_id')) {
            $query->where('estudiante_id', $request->input('estudiante_id'));
        }

        if ($request->has('seccion_id')) {
            $query->where('seccion_id', $request->input('seccion_id'));
        }

        return response()->json($query->get());
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
