<?php
namespace App\Swagger\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    title: "PartnerChallenge",
    description: "",
    properties: [
        new OA\Property(property: "id", type: "string", format: "uuid", example: 'chal-9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d'),
        new OA\Property(property: "program_id", type: "string", format: "uuid", example: 'program-9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d'),
        new OA\Property(property: "title", type: "string", example: 'Desafio Inter-Escolar de Literacia Financeira'),
        new OA\Property(property: "description", type: "string", example: 'As 3 melhores turmas ganham equipamentos de informática para a escola.'),
        new OA\Property(property: "reward_pool", type: "number", format: "float", example: 1000000.0),
        new OA\Property(property: "target_schools_count", type: "integer", example: 10),
        new OA\Property(property: "participating_schools_count", type: "integer", example: 8),
        new OA\Property(property: "start_date", type: "string", format: "date", example: '2026-09-15'),
        new OA\Property(property: "end_date", type: "string", format: "date", example: '2026-11-30'),
        new OA\Property(property: "status", type: "string", example: 'active')
    ]
)]
class PartnerChallenge {}
