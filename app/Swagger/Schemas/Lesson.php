<?php
namespace App\Swagger\Schemas;
use OpenApi\Attributes as OA;

#[OA\Schema(
    title: "Lesson",
    description: "Lição do RPG / Educação",
    properties: [
        new OA\Property(property: "id", type: "string", format: "uuid"),
        new OA\Property(property: "title", type: "string", example: "Introdução à Poupança"),
        new OA\Property(property: "description", type: "string", example: "Aprende o valor do dinheiro."),
        new OA\Property(property: "reward_points", type: "integer", example: 50),
        new OA\Property(property: "created_at", type: "string", format: "date-time")
    ]
)]
class Lesson {}
