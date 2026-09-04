<?php
namespace App\Swagger\Schemas;
use OpenApi\Attributes as OA;

#[OA\Schema(
    title: "Profile",
    description: "Perfil do Utilizador",
    properties: [
        new OA\Property(property: "id", type: "string", format: "uuid"),
        new OA\Property(property: "user_id", type: "string", format: "uuid"),
        new OA\Property(property: "avatar_url", type: "string", example: "https://example.com/avatar.jpg"),
        new OA\Property(property: "phone", type: "string", example: "+244900000000"),
        new OA\Property(property: "birth_date", type: "string", format: "date", example: "1990-01-01"),
        new OA\Property(property: "created_at", type: "string", format: "date-time")
    ]
)]
class Profile {}
