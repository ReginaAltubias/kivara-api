<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: "1.0.0",
    description: "Documentação oficial da API do ecossistema KIVARA (B2B2C).",
    title: "KIVARA API",
    contact: new OA\Contact(email: "admin@kivara.co.ao")
)]
#[OA\Server(
    url: L5_SWAGGER_CONST_HOST,
    description: "KIVARA API Server"
)]
#[OA\SecurityScheme(
    securityScheme: "bearerAuth",
    type: "http",
    scheme: "bearer",
    bearerFormat: "JWT"
)]
#[OA\Get(
    path: "/api/v1/health",
    summary: "Health Check",
    description: "Verifica o estado da API",
    responses: [
        new OA\Response(response: 200, description: "API a funcionar corretamente")
    ]
)]
abstract class Controller
{
    //
}
