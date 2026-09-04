<?php

namespace App\Enums;

enum LessonContentType: string
{
    case VIDEO = 'video';
    case TEXT = 'text';
    case INTERACTIVE = 'interactive';
}
