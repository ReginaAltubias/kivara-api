<?php
namespace App\Swagger\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    title: "ErrorResponse",
    description: "",
    properties: [
        new OA\Property(property: "status", type: "string", example: 'error'),
        new OA\Property(property: "message", type: "string", example: 'Mensagem descritiva do erro.'),
        new OA\Property(property: "error_code", type: "string", example: 'RESOURCE_NOT_FOUND')
    ]
)]
class ErrorResponse {}
