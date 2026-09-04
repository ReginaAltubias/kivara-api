<?php
namespace App\Swagger\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    title: "Task",
    description: "",
    properties: [
        new OA\Property(property: "id", type: "string", format: "uuid", example: 'task-9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d'),
        new OA\Property(property: "child_profile_id", type: "string", format: "uuid", example: '9c1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6f'),
        new OA\Property(property: "title", type: "string", example: 'Organizar a mesa de estudo'),
        new OA\Property(property: "description", type: "string", example: 'Limpar o quarto e organizar os cadernos escolares.'),
        new OA\Property(property: "reward_amount", type: "number", format: "float", example: 500.0),
        new OA\Property(property: "xp_reward", type: "integer", example: 50),
        new OA\Property(property: "due_date", type: "string", format: "date", example: '2026-08-28'),
        new OA\Property(property: "status", type: "string", example: 'pending'),
        new OA\Property(property: "proof_image_url", type: "string", example: 'https://api.kivara.ao/tasks/proof123.jpg'),
        new OA\Property(property: "feedback", type: "string", example: 'Muito bem organizado!'),
        new OA\Property(property: "created_at", type: "string", format: "date-time", example: '2026-08-27T10:00:00Z')
    ]
)]
class Task {}
