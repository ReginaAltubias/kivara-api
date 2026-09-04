<?php
namespace App\Swagger\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    title: "ValidationErrorResponse",
    description: "",
    properties: [
        new OA\Property(property: "status", type: "string", example: 'error'),
        new OA\Property(property: "message", type: "string", example: 'Os dados fornecidos são inválidos.'),
        new OA\Property(property: "errors", type: "object", example: array (
  'email' => 
  array (
    0 => 'O campo email é obrigatório.',
    1 => 'O email deve ser um endereço válido.',
  ),
  'amount' => 
  array (
    0 => 'O montante deve ser superior a zero.',
  ),
))
    ]
)]
class ValidationErrorResponse {}
