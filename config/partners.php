<?php

return [
    // Quotas por parceiro (ate existir um plano proprio para parceiros).
    'max_programs' => env('PARTNER_MAX_PROGRAMS', 20),
    'max_children' => env('PARTNER_MAX_CHILDREN', 100000),
];
