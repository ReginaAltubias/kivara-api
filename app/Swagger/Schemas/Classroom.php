<?php
namespace App\Swagger\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    title: "Classroom",
    description: "",
    properties: [
        new OA\Property(property: "id", type: "string", format: "uuid", example: 'class-9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d'),
        new OA\Property(property: "tenant_id", type: "string", format: "uuid", example: '8a1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6e'),
        new OA\Property(property: "name", type: "string", example: '5º Ano A - Turma Girassol'),
        new OA\Property(property: "grade_level", type: "string", example: '5'),
        new OA\Property(property: "academic_year", type: "string", example: '2025/2026'),
        new OA\Property(property: "teacher_id", type: "string", format: "uuid", example: '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d'),
        new OA\Property(property: "students_count", type: "integer", example: 28),
        new OA\Property(property: "status", type: "string", example: 'active'),
        new OA\Property(property: "created_at", type: "string", format: "date-time", example: '2026-08-27T10:00:00Z')
    ]
)]
class Classroom {}
