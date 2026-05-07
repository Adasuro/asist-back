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

    public function sectionDaily($sectionId)
    {
        return response()->json($this->attendanceService->getDailyAttendance($sectionId));
    }
}
