<?php

namespace App\Enums;

enum MissionTaskType: string
{
    case QUIZ = 'quiz';
    case REAL_WORLD_ACTION = 'real_world_action';
    case UPLOAD_PROOF = 'upload_proof';
}
