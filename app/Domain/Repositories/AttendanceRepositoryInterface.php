<?php

namespace App\Domain\Repositories;

interface AttendanceRepositoryInterface
{
    public function register(array $data);
    public function findByStudentAndDate($studentId, $date);
    public function listBySectionAndDate($sectionId, $date);
}
