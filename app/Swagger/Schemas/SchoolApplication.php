<?php
namespace App\Swagger\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    title: "SchoolApplication",
    description: "",
    properties: [
        new OA\Property(property: "id", type: "string", format: "uuid", example: 'app-9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d'),
        new OA\Property(property: "school_name", type: "string", example: 'Colégio Esperança do Saber'),
        new OA\Property(property: "contact_name", type: "string", example: 'Prof. Teresa Silva'),
        new OA\Property(property: "contact_email", type: "string", format: "email", example: 'teresa.silva@colegioesperanca.ao'),
        new OA\Property(property: "contact_phone", type: "string", example: '+244923112233'),
        new OA\Property(property: "address", type: "string", example: 'Rua Comandante Gika, Luanda'),
        new OA\Property(property: "city", type: "string", example: 'Luanda'),
        new OA\Property(property: "students_estimate", type: "integer", example: 350),
        new OA\Property(property: "status", type: "string", example: 'pending'),
        new OA\Property(property: "rejection_reason", type: "string"),
        new OA\Property(property: "created_at", type: "string", format: "date-time", example: '2026-08-27T10:00:00Z')
    ]
)]
class SchoolApplication {}
