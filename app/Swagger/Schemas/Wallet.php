<?php
namespace App\Swagger\Schemas;
use OpenApi\Attributes as OA;

#[OA\Schema(
    title: "Wallet",
    description: "Carteira / Cofre Digital",
    properties: [
        new OA\Property(property: "id", type: "string", format: "uuid"),
        new OA\Property(property: "user_id", type: "string", format: "uuid"),
        new OA\Property(property: "currency_id", type: "string", format: "uuid"),
        new OA\Property(property: "balance", type: "number", format: "float", example: 15000.50),
        new OA\Property(property: "type", type: "string", example: "savings"),
        new OA\Property(property: "created_at", type: "string", format: "date-time")
    ]
)]
class Wallet {}
