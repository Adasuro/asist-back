<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Repositories\StudentRepositoryInterface;
use App\Models\Estudiante;
use Illuminate\Pagination\LengthAwarePaginator;

class EloquentStudentRepository implements StudentRepositoryInterface
{
    public function getAll(array $filters): LengthAwarePaginator
    {
        $query = Estudiante::with(['seccion.grado']);

        if (isset($filters['secciones_ids'])) {
            $query->whereIn('seccion_id', $filters['secciones_ids']);
        }

        if (isset($filters['grado_id'])) {
            $query->whereHas('seccion', function($q) use ($filters) {
                $q->where('grado_id', $filters['grado_id']);
            });
        }

        if (isset($filters['seccion_id'])) {
            $query->where('seccion_id', $filters['seccion_id']);
        }

        if (isset($filters['search'])) {
            $query->where(function($q) use ($filters) {
                $q->where('nombre_completo', 'like', "%{$filters['search']}%")
                  ->orWhere('dni', 'like', "%{$filters['search']}%")
                  ->orWhere('codigo_sistema', 'like', "%{$filters['search']}%");
            });
        }

        return $query->paginate(20);
    }

    public function findByDni(string $dni): ?Estudiante
    {
        return Estudiante::where('dni', $dni)->first();
    }

    public function save(array $data): Estudiante
    {
        return Estudiante::create($data);
    }

    public function updateOrCreate(array $search, array $data): Estudiante
    {
        return Estudiante::updateOrCreate($search, $data);
    }

    public function findById(string $id): ?Estudiante
    {
        return Estudiante::with(['seccion.grado'])->find($id);
    }

    public function update(string $id, array $data): Estudiante
    {
        $student = Estudiante::findOrFail($id);
        $student->update($data);
        return $student;
    }

    public function delete(string $id): bool
    {
        $student = Estudiante::find($id);
        if ($student) {
            return $student->delete();
        }
        return false;
    }
}
