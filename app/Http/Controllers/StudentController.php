<?php

namespace App\Http\Controllers;

use App\Application\Services\StudentService;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    private $studentService;

    public function __construct(StudentService $studentService)
    {
        $this->studentService = $studentService;
    }

    public function index(Request $request)
    {
        $filters = $request->only(['grado_id', 'seccion_id', 'search']);
        
        if ($request->user()->rol === 'auxiliar') {
            $filters['secciones_ids'] = $request->user()->secciones()->pluck('secciones.id')->toArray();
        }

        return response()->json($this->studentService->listStudents($filters));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre_completo' => 'required|string|max:255',
            'dni' => 'required|string|digits:8',
            'seccion_id' => 'required|exists:secciones,id',
            'fecha_nacimiento' => 'nullable|date',
            'telefono' => 'nullable|string',
            'direccion' => 'nullable|string',
        ]);

        $student = $this->studentService->registerStudent($validated);

        return response()->json([
            'message' => 'Estudiante registrado correctamente.',
            'student' => $student
        ]);
    }

    public function show(Request $request, $id)
    {
        $student = $this->studentService->getStudentById($id);
        if (!$student) {
            return response()->json(['message' => 'Estudiante no encontrado.'], 404);
        }

        if ($request->user()->rol === 'auxiliar') {
            $assignedSections = $request->user()->secciones()->pluck('secciones.id')->toArray();
            if (!in_array($student->seccion_id, $assignedSections)) {
                return response()->json(['message' => 'No tiene permiso para acceder a este estudiante.'], 403);
            }
        }

        return response()->json($student);
    }

    public function update(Request $request, $id)
    {
        $student = $this->studentService->getStudentById($id);
        if (!$student) {
            return response()->json(['message' => 'Estudiante no encontrado.'], 404);
        }

        if ($request->user()->rol === 'auxiliar') {
            $assignedSections = $request->user()->secciones()->pluck('secciones.id')->toArray();
            if (!in_array($student->seccion_id, $assignedSections)) {
                return response()->json(['message' => 'No tiene permiso para actualizar a este estudiante.'], 403);
            }
        }

        $validated = $request->validate([
            'nombre_completo' => 'required|string|max:255',
            'dni' => 'required|string|digits:8',
            'seccion_id' => 'required|exists:secciones,id',
            'fecha_nacimiento' => 'nullable|date',
            'telefono' => 'nullable|string',
            'direccion' => 'nullable|string',
        ]);

        $updatedStudent = $this->studentService->updateStudent($id, $validated);

        return response()->json([
            'message' => 'Estudiante actualizado correctamente.',
            'student' => $updatedStudent
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $student = $this->studentService->getStudentById($id);
        if (!$student) {
            return response()->json(['message' => 'Estudiante no encontrado.'], 404);
        }

        if ($request->user()->rol === 'auxiliar') {
            $assignedSections = $request->user()->secciones()->pluck('secciones.id')->toArray();
            if (!in_array($student->seccion_id, $assignedSections)) {
                return response()->json(['message' => 'No tiene permiso para eliminar a este estudiante.'], 403);
            }
        }

        $this->studentService->deleteStudent($id);
        return response()->json(['message' => 'Estudiante eliminado correctamente.']);
    }

    public function importCSV(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:2048'
        ]);

        try {
            $stats = $this->studentService->importFromCSV($request->file('file'));
            return response()->json([
                'message' => 'Proceso de importación finalizado.',
                'stats' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error crítico: ' . $e->getMessage()], 500);
        }
    }

    public function downloadTemplate()
    {
        $callback = $this->studentService->getImportTemplate();
        
        return response()->stream($callback, 200, [
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=plantilla_estudiantes.csv",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ]);
    }
}
