<?php
namespace App\Swagger\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    title: "LoginBanner",
    description: "",
    properties: [
        new OA\Property(property: "id", type: "string", format: "uuid", example: 'banner-9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d'),
        new OA\Property(property: "title", type: "string", example: 'Semana Global do Dinheiro 2026'),
        new OA\Property(property: "image_url", type: "string", example: 'https://api.kivara.ao/banners/global-money-week.jpg'),
        new OA\Property(property: "link_url", type: "string", example: 'https://kivara.ao/gmw'),
        new OA\Property(property: "order", type: "integer", example: 1),
        new OA\Property(property: "active_from", type: "string", format: "date-time", example: '2026-09-01T00:00:00Z'),
        new OA\Property(property: "active_until", type: "string", format: "date-time", example: '2026-09-30T23:59:59Z'),
        new OA\Property(property: "is_active", type: "boolean", example: true)
    ]
)]
class LoginBanner {}
