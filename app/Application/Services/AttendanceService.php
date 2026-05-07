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
        $currentTime = $now->format('H:i:s');
        $currentDate = $now->toDateString();
        
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

        return $this->attendanceRepository->register($registrationData);
    }

    public function getDailyAttendance($sectionId)
    {
        return $this->attendanceRepository->listBySectionAndDate($sectionId, date('Y-m-d'));
    }
}
