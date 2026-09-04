<?php
namespace App\Swagger\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    title: "ChildProfile",
    description: "",
    properties: [
        new OA\Property(property: "id", type: "string", format: "uuid", example: '9c1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6f'),
        new OA\Property(property: "family_id", type: "string", format: "uuid", example: '9d1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb70'),
        new OA\Property(property: "name", type: "string", example: 'Kieza Fernandes'),
        new OA\Property(property: "nickname", type: "string", example: 'Kieza'),
        new OA\Property(property: "avatar_url", type: "string", example: 'https://api.kivara.ao/avatars/kieza.png'),
        new OA\Property(property: "age_group", type: "string", example: 'kids'),
        new OA\Property(property: "date_of_birth", type: "string", format: "date", example: '2016-04-12'),
        new OA\Property(property: "daily_screen_time_minutes", type: "integer", example: 60),
        new OA\Property(property: "level", type: "integer", example: 4),
        new OA\Property(property: "xp", type: "integer", example: 1250),
        new OA\Property(property: "coins_balance", type: "number", format: "float", example: 350.0),
        new OA\Property(property: "created_at", type: "string", format: "date-time", example: '2026-08-27T10:00:00Z')
    ]
)]
class ChildProfile {}
