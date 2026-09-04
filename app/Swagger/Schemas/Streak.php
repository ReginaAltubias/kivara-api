<?php
namespace App\Swagger\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    title: "Streak",
    description: "",
    properties: [
        new OA\Property(property: "id", type: "string", format: "uuid", example: 'streak-9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d'),
        new OA\Property(property: "child_profile_id", type: "string", format: "uuid", example: '9c1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6f'),
        new OA\Property(property: "current_streak", type: "integer", example: 7),
        new OA\Property(property: "longest_streak", type: "integer", example: 15),
        new OA\Property(property: "last_activity_date", type: "string", format: "date", example: '2026-08-27'),
        new OA\Property(property: "frozen_days_remaining", type: "integer", example: 2),
        new OA\Property(property: "is_active_today", type: "boolean", example: true)
    ]
)]
class Streak {}
