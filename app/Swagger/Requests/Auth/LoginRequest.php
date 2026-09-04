<?php
namespace App\Swagger\Requests\Auth;
use OpenApi\Attributes as OA;

#[OA\Schema(
    title: "Login Request",
    required: ["email", "password", "tenant_id"],
    properties: [
        new OA\Property(property: "email", type: "string", format: "email"),
        new OA\Property(property: "password", type: "string", format: "password"),
        new OA\Property(property: "tenant_id", type: "string", format: "uuid")
    ]
)]
class LoginRequest {}
