<?php

namespace App\Application\Services;

use App\Domain\Repositories\AttendanceRepositoryInterface;
use App\Models\Estudiante;
use App\Models\Asistencia;
use App\Models\Alerta;
use App\Models\RegistroAsistenciaOficial;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AttendanceService
{
    private $attendanceRepository;

    public function __construct(AttendanceRepositoryInterface $attendanceRepository)
    {
        $this->attendanceRepository = $attendanceRepository;
    }

    public function registerAttendance(array $data)
    {
        $student = null;

        if (isset($data['estudiante_id'])) {
            $student = Estudiante::find($data['estudiante_id']);
        } elseif (isset($data['codigo_sistema'])) {
            $student = Estudiante::where('codigo_sistema', $data['codigo_sistema'])->first();
        }

        if (!$student) {
            throw new \Exception("Estudiante no encontrado.");
        }

        $now = now();
        $currentDate = $now->toDateString();

        // Validar que solo se registre/edite asistencia del día actual
        if (isset($data['fecha']) && $data['fecha'] !== $currentDate) {
            throw new \Exception("Solo se pueden registrar o editar asistencias del día en curso.");
        }

        $existing = Asistencia::where('estudiante_id', $student->id)
            ->where('fecha', $currentDate)
            ->first();

        // Si ya existe registro de hoy, aplicar reglas estrictas de edición
        if ($existing) {
            $newStatus = $data['estado'] ?? 'presente';
            
            // Si el método es 'codigo' (QR), siempre transiciona a tardanza
            if (($data['metodo_registro'] ?? 'manual') === 'codigo') {
                $newStatus = 'tardanza';
            }

            // La única transición permitida es de FALTA a TARDANZA dentro del día
            if ($existing->estado !== 'falta' || $newStatus !== 'tardanza') {
                throw new \Exception("BLOQUEADO: Solo se permite actualizar el estado de 'Falta' a 'Tardanza' durante el día en curso.");
            }

            $existing->estado = 'tardanza';
            $existing->hora_llegada = $now->format('H:i:s');
            $existing->metodo_registro = $data['metodo_registro'] ?? 'manual';
            $existing->registrado_por = Auth::id();
            $existing->save();

            try {
                $this->checkAndGenerateAlerts($student->id);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("Error al generar alertas: " . $e->getMessage());
            }

            return $existing;
        }

        // Si no existe registro previo, significa que no se ha hecho la carga masiva inicial
        throw new \Exception("BLOQUEADO: Debe registrar la asistencia masiva inicial de la sección antes de registrar tardanzas individuales.");
    }

    public function registerBulkAttendance(array $data)
    {
        $sectionId = $data['seccion_id'];
        $now = now();
        $currentDate = $now->toDateString();

        // 1. Validar que no exista oficialización previa para hoy
        $isOfficial = RegistroAsistenciaOficial::where('seccion_id', $sectionId)
            ->where('fecha', $currentDate)
            ->exists();

        if ($isOfficial) {
            throw new \Exception("La asistencia de hoy para esta sección ya ha sido registrada y oficializada.");
        }

        // 2. Procesar la lista de estudiantes
        $studentsData = $data['students'] ?? [];
        if (empty($studentsData)) {
            throw new \Exception("La lista de alumnos no puede estar vacía.");
        }

        DB::transaction(function () use ($studentsData, $sectionId, $currentDate, $now) {
            foreach ($studentsData as $item) {
                $studentId = $item['estudiante_id'];
                $status = $item['estado'] ?? 'presente';

                if (!in_array($status, ['presente', 'falta'])) {
                    throw new \Exception("Para el registro inicial, los estados permitidos son únicamente 'presente' o 'falta'.");
                }

                // Registrar o actualizar asistencia
                Asistencia::updateOrCreate(
                    [
                        'estudiante_id' => $studentId,
                        'fecha' => $currentDate,
                    ],
                    [
                        'registrado_por' => Auth::id(),
                        'seccion_id' => $sectionId,
                        'estado' => $status,
                        'hora_llegada' => $now->format('H:i:s'),
                        'metodo_registro' => 'manual',
                    ]
                );

                // Disparar alertas correspondientes si es falta
                if ($status === 'falta') {
                    $this->checkAndGenerateAlerts($studentId);
                }
            }

            // 3. Crear el registro de oficialización para cerrar el registro inicial
            RegistroAsistenciaOficial::create([
                'seccion_id' => $sectionId,
                'fecha' => $currentDate,
                'oficializado_por' => Auth::id()
            ]);
        });

        return true;
    }

    public function officiateSection($sectionId)
    {
        $fecha = now()->toDateString();
        
        // 1. Verificar si ya es oficial
        $exists = RegistroAsistenciaOficial::where('seccion_id', $sectionId)
            ->where('fecha', $fecha)
            ->exists();
            
        if ($exists) {
            throw new \Exception("La asistencia de hoy para esta sección ya ha sido confirmada.");
        }
        
        // 2. Marcar faltas automáticas para alumnos sin registro
        $students = Estudiante::where('seccion_id', $sectionId)->get();
        foreach ($students as $student) {
            $hasAttendance = Asistencia::where('estudiante_id', $student->id)
                ->where('fecha', $fecha)
                ->exists();
                
            if (!$hasAttendance) {
                $this->attendanceRepository->register([
                    'estudiante_id' => $student->id,
                    'registrado_por' => Auth::id(),
                    'seccion_id' => $sectionId,
                    'fecha' => $fecha,
                    'estado' => 'falta',
                    'metodo_registro' => 'manual',
                    'observacion' => 'Marcado automáticamente al confirmar asistencia.',
                ]);
                
                $this->checkAndGenerateAlerts($student->id);
            }
        }
        
        // 3. Registrar oficialización
        return RegistroAsistenciaOficial::create([
            'seccion_id' => $sectionId,
            'fecha' => $fecha,
            'oficializado_por' => Auth::id()
        ]);
    }

    public function checkAndGenerateAlerts($studentId)
    {
// ... rest of the code stays same
        $student = Estudiante::find($studentId);
        if (!$student) return;

        $now = now();

        // 1. Lógica de Tardanzas (Mensual - 3 Injustificadas = 1 Falta)
        $tardanzasInjustificadas = Asistencia::where('estudiante_id', $studentId)
            ->where('estado', 'tardanza')
            ->whereMonth('fecha', $now->month)
            ->whereYear('fecha', $now->year)
            ->whereDoesntHave('justificacion')
            ->count();

        $faltasPorTardanza = floor($tardanzasInjustificadas / 3);

        if ($faltasPorTardanza > 0) {
            Alerta::updateOrCreate(
                [
                    'estudiante_id' => $studentId,
                    'tipo' => 'tardanzas_a_falta',
                    'resuelta' => false,
                    // Usamos una clave para identificar el mes y evitar duplicados
                    'mensaje' => "El alumno ha acumulado {$tardanzasInjustificadas} tardanzas en el mes de " . $now->translatedFormat('F') . ", equivalentes a {$faltasPorTardanza} falta(s)."
                ],
                [
                    'mensaje' => "El alumno ha acumulado {$tardanzasInjustificadas} tardanzas en el mes de " . $now->translatedFormat('F') . ", equivalentes a {$faltasPorTardanza} falta(s)."
                ]
            );
        }

        // 2. Lógica de Faltas Excesivas (Anual - 5 Injustificadas)
        $faltasInjustificadas = Asistencia::where('estudiante_id', $studentId)
            ->where('estado', 'falta')
            ->whereYear('fecha', $now->year)
            ->whereDoesntHave('justificacion')
            ->count();

        if ($faltasInjustificadas >= 5) {
            Alerta::firstOrCreate(
                [
                    'estudiante_id' => $studentId,
                    'tipo' => 'faltas_excesivas',
                    'resuelta' => false
                ],
                [
                    'mensaje' => "¡ALERTA CRÍTICA! El alumno ha alcanzado {$faltasInjustificadas} faltas injustificadas en lo que va del año."
                ]
            );
        } else {
            // Si el número baja de 5 (por una justificación tardía), marcamos la alerta como resuelta
            Alerta::where('estudiante_id', $studentId)
                ->where('tipo', 'faltas_excesivas')
                ->where('resuelta', false)
                ->delete(); // O update(['resuelta' => true])
        }
    }

    public function getDailyAttendance($sectionId)
    {
        $fecha = now()->toDateString();
        $asistencias = $this->attendanceRepository->listBySectionAndDate($sectionId, $fecha);
        $isOfficial = RegistroAsistenciaOficial::where('seccion_id', $sectionId)
            ->where('fecha', $fecha)
            ->exists();

        return [
            'asistencias' => $asistencias,
            'is_official' => $isOfficial
        ];
    }
}
