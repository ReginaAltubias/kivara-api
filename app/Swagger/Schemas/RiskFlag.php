<?php
namespace App\Swagger\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    title: "RiskFlag",
    description: "",
    properties: [
        new OA\Property(property: "id", type: "string", format: "uuid", example: 'risk-9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d'),
        new OA\Property(property: "user_id", type: "string", format: "uuid", example: 'user-123'),
        new OA\Property(property: "risk_level", type: "string", example: 'high'),
        new OA\Property(property: "reason", type: "string", example: 'Múltiplas tentativas de login com IP suspeito'),
        new OA\Property(property: "status", type: "string", example: 'open'),
        new OA\Property(property: "details", type: "object", example: array (
  'failed_attempts' => 5,
  'country' => 'Unknown',
)),
        new OA\Property(property: "created_at", type: "string", format: "date-time", example: '2026-08-27T10:00:00Z')
    ]
)]
class RiskFlag {}
