<?php

namespace App\Domain\Repositories;

use App\Models\Estudiante;
use Illuminate\Pagination\LengthAwarePaginator;

interface StudentRepositoryInterface
{
    public function getAll(array $filters): LengthAwarePaginator;
    public function findByDni(string $dni): ?Estudiante;
    public function findById(string $id): ?Estudiante;
    public function save(array $data): Estudiante;
    public function update(string $id, array $data): Estudiante;
    public function delete(string $id): bool;
    public function updateOrCreate(array $search, array $data): Estudiante;
}
