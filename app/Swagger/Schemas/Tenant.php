<?php
namespace App\Swagger\Schemas;
use OpenApi\Attributes as OA;

#[OA\Schema(
    title: "Tenant",
    description: "Modelo base de Tenant (Escola, Parceiro, etc.)",
    properties: [
        new OA\Property(property: "id", type: "string", format: "uuid"),
        new OA\Property(property: "name", type: "string", example: "Escola Primária Kivara"),
        new OA\Property(property: "slug", type: "string", example: "escola-primaria-kivara"),
        new OA\Property(property: "type", type: "string", example: "school"),
        new OA\Property(property: "created_at", type: "string", format: "date-time")
    ]
)]
class Tenant {}
