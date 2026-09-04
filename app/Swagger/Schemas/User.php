<?php
namespace App\Swagger\Schemas;
use OpenApi\Attributes as OA;

#[OA\Schema(
    title: "User",
    description: "Modelo base de Utilizador",
    properties: [
        new OA\Property(property: "id", type: "string", format: "uuid", example: "9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d"),
        new OA\Property(property: "name", type: "string", example: "Prof. Carlos Manuel"),
        new OA\Property(property: "email", type: "string", format: "email", example: "coordenacao@escola.ao"),
        new OA\Property(property: "role", type: "string", example: "school_admin"),
        new OA\Property(property: "must_change_password", type: "boolean", example: false),
        new OA\Property(property: "created_at", type: "string", format: "date-time")
    ]
)]
class User {}
