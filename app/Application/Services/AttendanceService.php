<?php

namespace App\Application\Services;

use App\Domain\Repositories\AttendanceRepositoryInterface;
use App\Models\Estudiante;
use App\Models\Asistencia;
use App\Models\Alerta;
use App\Models\RegistroAsistenciaOficial;
use Illuminate\Support\Facades\Auth;

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

        // Verificar si la asistencia ya ha sido confirmada (oficializada)
        $isOfficial = RegistroAsistenciaOficial::where('seccion_id', $student->seccion_id)
            ->where('fecha', $currentDate)
            ->exists();

        if ($isOfficial) {
            $existing = Asistencia::where('estudiante_id', $student->id)
                ->where('fecha', $currentDate)
                ->first();

            $newStatus = $data['estado'] ?? 'presente';

            // Regla 1: No se puede marcar como "Presente" si ya es oficial
            if ($newStatus === 'presente') {
                throw new \Exception("BLOQUEADO: No se puede registrar como 'Presente' después de la confirmación oficial.");
            }

            // Regla 2: Si el registro previo era "Presente", ya no se puede modificar
            if ($existing && $existing->estado === 'presente') {
                throw new \Exception("BLOQUEADO: Los registros marcados como 'Presente' son inmutables tras la confirmación.");
            }
        }
        
        $currentTime = $now->format('H:i:s');
        
        $status = $data['estado'] ?? 'presente';

        // Lógica de automatización por horario para registros por código
        if (($data['metodo_registro'] ?? 'manual') === 'codigo') {
            $entryTime = $now->format('H:i');
            
            if ($entryTime >= '10:18' && $entryTime <= '10:25') {
                $status = 'presente';
            } else {
                $status = 'tardanza';
            }
        }

        $registrationData = [
            'estudiante_id' => $student->id,
            'registrado_por' => Auth::id(),
            'seccion_id' => $student->seccion_id,
            'fecha' => $currentDate,
            'estado' => $status,
            'hora_llegada' => $currentTime,
            'metodo_registro' => $data['metodo_registro'] ?? 'manual',
            'observacion' => $data['observacion'] ?? null,
        ];

        $attendance = $this->attendanceRepository->register($registrationData);

        // Disparar validación de alertas de forma segura
        try {
            $this->checkAndGenerateAlerts($student->id);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Error al generar alertas: " . $e->getMessage());
        }

        return $attendance;
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
