<?php
namespace App\Swagger\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    title: "StandardSuccessResponse",
    description: "",
    properties: [
        new OA\Property(property: "status", type: "string", example: 'success'),
        new OA\Property(property: "message", type: "string", example: 'Operação executada com sucesso.'),
        new OA\Property(property: "data", type: "object")
    ]
)]
class StandardSuccessResponse {}
