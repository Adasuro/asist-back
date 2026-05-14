<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;

class AttendanceExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithTitle
{
    protected $data;
    protected $title;

    public function __construct($data, $title = 'Reporte de Asistencia')
    {
        $this->data = $data;
        $this->title = $title;
    }

    public function collection()
    {
        return $this->data;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function headings(): array
    {
        return [
            'Estudiante',
            'Fecha',
            'Estado',
            'Justificado',
            'Sección',
            'Grado'
        ];
    }

    public function map($row): array
    {
        return [
            $row->nombre_completo,
            $row->fecha,
            ucfirst($row->estado),
            $row->justificacion_id ? 'Sí' : 'No',
            $row->seccion_nombre,
            $row->grado_nombre,
        ];
    }
}
