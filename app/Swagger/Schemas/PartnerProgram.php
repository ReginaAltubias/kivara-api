<?php
namespace App\Swagger\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    title: "PartnerProgram",
    description: "",
    properties: [
        new OA\Property(property: "id", type: "string", format: "uuid", example: 'program-9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d'),
        new OA\Property(property: "partner_id", type: "string", format: "uuid", example: 'partner-9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d'),
        new OA\Property(property: "title", type: "string", example: 'Bolsa Jovem Empreendedor 2026'),
        new OA\Property(property: "description", type: "string", example: 'Iniciativa de fomento ao empreendedorismo em escolas públicas.'),
        new OA\Property(property: "allocated_budget", type: "number", format: "float", example: 5000000.0),
        new OA\Property(property: "spent_budget", type: "number", format: "float", example: 1250000.0),
        new OA\Property(property: "currency", type: "string", example: 'AOA'),
        new OA\Property(property: "start_date", type: "string", format: "date", example: '2026-09-01'),
        new OA\Property(property: "end_date", type: "string", format: "date", example: '2026-12-31'),
        new OA\Property(property: "status", type: "string", example: 'active')
    ]
)]
class PartnerProgram {}
