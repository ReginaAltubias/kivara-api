<?php
namespace App\Swagger\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    title: "AllowanceConfig",
    description: "",
    properties: [
        new OA\Property(property: "id", type: "string", format: "uuid", example: 'allowance-9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d'),
        new OA\Property(property: "child_profile_id", type: "string", format: "uuid", example: '9c1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6f'),
        new OA\Property(property: "parent_id", type: "string", format: "uuid", example: '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d'),
        new OA\Property(property: "amount", type: "number", format: "float", example: 5000.0),
        new OA\Property(property: "currency", type: "string", example: 'AOA'),
        new OA\Property(property: "frequency", type: "string", example: 'weekly'),
        new OA\Property(property: "day_of_week", type: "integer", example: 5),
        new OA\Property(property: "day_of_month", type: "integer", example: 1),
        new OA\Property(property: "auto_transfer", type: "boolean", example: true),
        new OA\Property(property: "split_to_vault_percentage", type: "number", format: "float", example: 20.0),
        new OA\Property(property: "last_processed_at", type: "string", format: "date-time", example: '2026-08-20T08:00:00Z'),
        new OA\Property(property: "next_payment_date", type: "string", format: "date", example: '2026-08-27'),
        new OA\Property(property: "status", type: "string", example: 'active')
    ]
)]
class AllowanceConfig {}
