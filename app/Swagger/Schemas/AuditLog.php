<?php
namespace App\Swagger\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    title: "AuditLog",
    description: "",
    properties: [
        new OA\Property(property: "id", type: "string", format: "uuid", example: 'audit-9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d'),
        new OA\Property(property: "user_id", type: "string", format: "uuid", example: '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d'),
        new OA\Property(property: "user_name", type: "string", example: 'Administrador Geral'),
        new OA\Property(property: "action", type: "string", example: 'USER_INVITATION_SENT'),
        new OA\Property(property: "entity_type", type: "string", example: 'User'),
        new OA\Property(property: "entity_id", type: "string", example: 'user-123'),
        new OA\Property(property: "ip_address", type: "string", example: '102.214.32.10'),
        new OA\Property(property: "user_agent", type: "string", example: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'),
        new OA\Property(property: "payload", type: "object", example: array (
  'email' => 'novo.professor@escola.ao',
  'role' => 'teacher',
)),
        new OA\Property(property: "created_at", type: "string", format: "date-time", example: '2026-08-27T10:00:00Z')
    ]
)]
class AuditLog {}
