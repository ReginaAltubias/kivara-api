<?php
namespace App\Swagger\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    title: "SubscriptionPlan",
    description: "",
    properties: [
        new OA\Property(property: "id", type: "string", format: "uuid", example: 'plan-9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d'),
        new OA\Property(property: "name", type: "string", example: 'Plano Escola Premium Plus'),
        new OA\Property(property: "slug", type: "string", example: 'escola-premium-plus'),
        new OA\Property(property: "price", type: "number", format: "float", example: 120000.0),
        new OA\Property(property: "interval", type: "string", example: 'yearly'),
        new OA\Property(property: "currency", type: "string", example: 'AOA'),
        new OA\Property(property: "max_students", type: "integer", example: 500),
        new OA\Property(property: "max_teachers", type: "integer", example: 25),
        new OA\Property(property: "features", type: "array", example: array (
  0 => 'Turmas Ilimitadas',
  1 => 'Exportação de Relatórios MEC',
  2 => 'API B2B de Integração',
), items: new OA\Items(type: "string")),
        new OA\Property(property: "is_active", type: "boolean", example: true)
    ]
)]
class SubscriptionPlan {}
