<?php
namespace App\Swagger\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    title: "Badge",
    description: "",
    properties: [
        new OA\Property(property: "id", type: "string", format: "uuid", example: 'badge-9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d'),
        new OA\Property(property: "name", type: "string", example: 'Super Poupador'),
        new OA\Property(property: "description", type: "string", example: 'Guardou 10.000 Kz no Cofre dos Sonhos.'),
        new OA\Property(property: "icon_url", type: "string", example: 'https://api.kivara.ao/badges/super-saver.png'),
        new OA\Property(property: "category", type: "string", example: 'savings'),
        new OA\Property(property: "xp_bonus", type: "integer", example: 250),
        new OA\Property(property: "unlocked_at", type: "string", format: "date-time", example: '2026-08-26T14:30:00Z')
    ]
)]
class Badge {}
