<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Repositories\AttendanceRepositoryInterface;
use App\Models\Asistencia;
use Illuminate\Support\Str;

class EloquentAttendanceRepository implements AttendanceRepositoryInterface
{
    public function register(array $data)
    {
        return Asistencia::updateOrCreate(
            [
                'estudiante_id' => $data['estudiante_id'],
                'fecha' => $data['fecha'] ?? now()->toDateString(),
            ],
            array_merge($data, [
                'id' => $data['id'] ?? (string) Str::uuid(),
                'hora_llegada' => $data['hora_llegada'] ?? now()->toTimeString(),
            ])
        );
    }

    public function findByStudentAndDate($studentId, $date)
    {
        return Asistencia::where('estudiante_id', $studentId)
            ->where('fecha', $date)
            ->first();
    }

    public function listBySectionAndDate($sectionId, $date)
    {
        return Asistencia::with(['estudiante', 'justificacion'])
            ->where('seccion_id', $sectionId)
            ->where('fecha', $date)
            ->get();
    }
}
