<?php

namespace App\Application\Services;

use App\Domain\Repositories\AttendanceRepositoryInterface;
use App\Models\Estudiante;
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
        
        $currentTime = $now->format('H:i:s');
        
        $status = $data['estado'] ?? 'presente';

        // Lógica de automatización por horario para registros por código
        if (($data['metodo_registro'] ?? 'manual') === 'codigo') {
            $entryTime = $now->format('H:i');
            
            if ($entryTime >= '07:40' && $entryTime <= '08:10') {
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

        // Disparar validación de alertas
        $this->checkAndGenerateAlerts($student->id);

        return $attendance;
    }

    public function checkAndGenerateAlerts($studentId)
    {
        $student = Estudiante::find($studentId);
        if (!$student) return;

        $now = now();

        // 1. Lógica de Tardanzas (Mensual - 3 Injustificadas = 1 Falta)
        $tardanzasInjustificadas = \App\Models\Asistencia::where('estudiante_id', $studentId)
            ->where('estado', 'tardanza')
            ->whereMonth('fecha', $now->month)
            ->whereYear('fecha', $now->year)
            ->whereDoesntHave('justificacion')
            ->count();

        $faltasPorTardanza = floor($tardanzasInjustificadas / 3);

        if ($faltasPorTardanza > 0) {
            \App\Models\Alerta::updateOrCreate(
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
        $faltasInjustificadas = \App\Models\Asistencia::where('estudiante_id', $studentId)
            ->where('estado', 'falta')
            ->whereYear('fecha', $now->year)
            ->whereDoesntHave('justificacion')
            ->count();

        if ($faltasInjustificadas >= 5) {
            \App\Models\Alerta::firstOrCreate(
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
            \App\Models\Alerta::where('estudiante_id', $studentId)
                ->where('tipo', 'faltas_excesivas')
                ->where('resuelta', false)
                ->delete(); // O update(['resuelta' => true])
        }
    }

    public function getDailyAttendance($sectionId)
    {
        return $this->attendanceRepository->listBySectionAndDate($sectionId, date('Y-m-d'));
    }
}
