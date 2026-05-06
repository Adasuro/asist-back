<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Estudiante;
use App\Models\Seccion;
use App\Models\Grado;
use Illuminate\Support\Str;

class EstudianteSeeder extends Seeder
{
    public function run(): void
    {
        // Obtener una sección válida (ej. 1ero Secundaria A)
        $grado = Grado::where('nombre', 'like', '%1ero Secundaria%')->first();
        if (!$grado) return;
        
        $seccion = Seccion::where('grado_id', $grado->id)->where('nombre', 'A')->first();
        if (!$seccion) return;

        $alumnos = [
            [
                'nombre_completo' => 'Juan Alberto Perez',
                'dni' => '71234567',
                'codigo_sistema' => 'EST-71234567-2026',
            ],
            [
                'nombre_completo' => 'Maria Fernanda Garcia',
                'dni' => '72345678',
                'codigo_sistema' => 'EST-72345678-2026',
            ],
            [
                'nombre_completo' => 'Carlos Antonio Lopez',
                'dni' => '73456789',
                'codigo_sistema' => 'EST-73456789-2026',
            ]
        ];

        foreach ($alumnos as $alumno) {
            Estudiante::updateOrCreate(
                ['dni' => $alumno['dni']],
                array_merge($alumno, [
                    'seccion_id' => $seccion->id,
                    'activo' => true
                ])
            );
        }
    }
}
