<?php
namespace App\Swagger\Responses;
use OpenApi\Attributes as OA;

#[OA\Response(
    response: "ValidationError",
    description: "Erros de validação nos dados enviados",
    content: new OA\JsonContent(
        properties: [
            new OA\Property(property: "message", type: "string", example: "Os dados fornecidos são inválidos."),
            new OA\Property(
                property: "errors",
                type: "object",
                example: ["email" => ["O campo email é obrigatório."]]
            )
        ]
    )
)]
class ValidationError {}
