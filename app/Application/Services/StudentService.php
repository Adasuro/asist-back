<?php

namespace App\Application\Services;

use App\Domain\Repositories\StudentRepositoryInterface;
use App\Models\Grado;
use App\Models\Seccion;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\UploadedFile;

class StudentService
{
    private $studentRepository;

    public function __construct(StudentRepositoryInterface $studentRepository)
    {
        $this->studentRepository = $studentRepository;
    }

    public function listStudents(array $filters)
    {
        return $this->studentRepository->getAll($filters);
    }

    public function registerStudent(array $data)
    {
        $codigo = $this->generateSystemCode($data['dni']);
        return $this->studentRepository->updateOrCreate(
            ['dni' => $data['dni']],
            array_merge($data, ['codigo_sistema' => $codigo])
        );
    }

    public function getImportTemplate()
    {
        $headers = ['nombre_completo', 'dni', 'grado', 'seccion', 'fecha_nacimiento', 'telefono', 'direccion'];
        $example = ['Juan Perez', '12345678', '1° Grado', 'A', '2010-05-15', '987654321', 'Av. Las Flores 123'];
        
        $callback = function() use ($headers, $example) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $headers);
            fputcsv($file, $example);
            fclose($file);
            flush(); // Asegura el envío inmediato de los datos
        };

        return $callback;
    }

    public function importFromCSV(UploadedFile $file)
    {
        $handle = fopen($file->getRealPath(), "r");
        $firstLine = fgets($handle);
        $delimiter = (str_contains($firstLine, ';')) ? ';' : ',';
        rewind($handle);

        $stats = ['success' => 0, 'updated' => 0, 'errors' => []];
        fgetcsv($handle, 0, $delimiter); // Skip header

        DB::beginTransaction();
        try {
            $rowNum = 2;
            while (($row = fgetcsv($handle, 0, $delimiter)) !== FALSE) {
                if (empty(array_filter($row))) continue;
                
                $result = $this->processCsvRow($row, $rowNum);
                if (isset($result['error'])) {
                    $stats['errors'][] = $result['error'];
                } else {
                    $stats[$result['type']]++;
                }
                $rowNum++;
            }
            fclose($handle);
            DB::commit();
            return $stats;
        } catch (\Exception $e) {
            fclose($handle);
            DB::rollBack();
            throw $e;
        }
    }

    private function processCsvRow(array $row, int $rowNum)
    {
        // Limpieza de datos
        $data = array_map('trim', $row);
        
        $nombre = $data[0] ?? '';
        $dni = $data[1] ?? '';
        $grado_nombre = $data[2] ?? '';
        $seccion_nombre = $data[3] ?? '';
        $fecha_nac = $data[4] ?? null;
        $telefono = $data[5] ?? '';
        $direccion = $data[6] ?? '';

        // Validaciones de Campos Obligatorios
        if (empty($nombre)) return ['error' => "Fila $rowNum: El nombre completo es obligatorio."];
        if (empty($dni)) return ['error' => "Fila $rowNum: El DNI es obligatorio."];
        if (!preg_match('/^[0-9]{8}$/', $dni)) return ['error' => "Fila $rowNum: El DNI debe tener 8 dígitos numéricos."];
        if (empty($grado_nombre)) return ['error' => "Fila $rowNum: El grado es obligatorio."];
        if (empty($seccion_nombre)) return ['error' => "Fila $rowNum: La sección es obligatoria."];

        // Validación de Estructura Académica
        $grado = Grado::where('nombre', 'like', "%{$grado_nombre}%")->first();
        if (!$grado) return ['error' => "Fila $rowNum: Grado '{$grado_nombre}' no encontrado en el sistema."];

        $seccion = Seccion::where('grado_id', $grado->id)
                          ->where('nombre', 'like', "%{$seccion_nombre}%")
                          ->first();
        if (!$seccion) return ['error' => "Fila $rowNum: La sección '{$seccion_nombre}' no existe para el grado '{$grado_nombre}'."];

        // Validación de Fecha (ISO Format: AAAA-MM-DD)
        if (!empty($fecha_nac)) {
            $dateObj = \DateTime::createFromFormat('Y-m-d', $fecha_nac);
            if (!$dateObj || $dateObj->format('Y-m-d') !== $fecha_nac) {
                // Intentar soporte para DD/MM/AAAA como fallback por si acaso el usuario lo hace manual
                $fallbackObj = \DateTime::createFromFormat('d/m/Y', $fecha_nac);
                if ($fallbackObj && $fallbackObj->format('d/m/Y') === $fecha_nac) {
                    $fecha_nac = $fallbackObj->format('Y-m-d');
                } else {
                    return ['error' => "Fila $rowNum: Formato de fecha inválido (usar AAAA-MM-DD)."];
                }
            }
        }

        $exists = $this->studentRepository->findByDni($dni);
        
        $this->studentRepository->updateOrCreate(
            ['dni' => $dni],
            [
                'nombre_completo' => $nombre,
                'seccion_id' => $seccion->id,
                'fecha_nacimiento' => $fecha_nac ?: null,
                'telefono' => $telefono,
                'direccion' => $direccion,
                'codigo_sistema' => $this->generateSystemCode($dni),
                'activo' => true
            ]
        );

        return ['type' => $exists ? 'updated' : 'success'];
    }

    private function generateSystemCode($dni)
    {
        return "EST-{$dni}-" . date('Y');
    }
}
