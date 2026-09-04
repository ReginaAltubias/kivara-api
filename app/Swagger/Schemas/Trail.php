<?php
namespace App\Swagger\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    title: "Trail",
    description: "",
    properties: [
        new OA\Property(property: "id", type: "string", format: "uuid", example: 'trail-9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d'),
        new OA\Property(property: "title", type: "string", example: 'Jornada das Finanças Divertidas'),
        new OA\Property(property: "slug", type: "string", example: 'jornada-financas-divertidas'),
        new OA\Property(property: "description", type: "string", example: 'Aprende desde as primeiras trocas até ao investimento no cofrinho.'),
        new OA\Property(property: "age_group", type: "string", example: 'kids'),
        new OA\Property(property: "icon", type: "string", example: 'piggy-bank'),
        new OA\Property(property: "color_theme", type: "string", example: '#4CAF50'),
        new OA\Property(property: "total_lessons", type: "integer", example: 12),
        new OA\Property(property: "is_published", type: "boolean", example: true),
        new OA\Property(property: "version", type: "integer", example: 1)
    ]
)]
class Trail {}
