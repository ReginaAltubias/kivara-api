<?php
namespace App\Swagger\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    title: "DreamVault",
    description: "",
    properties: [
        new OA\Property(property: "id", type: "string", format: "uuid", example: 'vault-9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d'),
        new OA\Property(property: "child_profile_id", type: "string", format: "uuid", example: '9c1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6f'),
        new OA\Property(property: "name", type: "string", example: 'Bicicleta Nova'),
        new OA\Property(property: "target_amount", type: "number", format: "float", example: 50000.0),
        new OA\Property(property: "current_amount", type: "number", format: "float", example: 27500.0),
        new OA\Property(property: "currency", type: "string", example: 'AOA'),
        new OA\Property(property: "category", type: "string", example: 'toys'),
        new OA\Property(property: "target_date", type: "string", format: "date", example: '2026-12-25'),
        new OA\Property(property: "auto_deposit_amount", type: "number", format: "float", example: 2000.0),
        new OA\Property(property: "auto_deposit_frequency", type: "string", example: 'monthly'),
        new OA\Property(property: "status", type: "string", example: 'active'),
        new OA\Property(property: "percentage_progress", type: "number", format: "float", example: 55.0)
    ]
)]
class DreamVault {}
