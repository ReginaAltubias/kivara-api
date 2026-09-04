<?php
namespace App\Swagger\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    title: "LedgerEntry",
    description: "",
    properties: [
        new OA\Property(property: "id", type: "string", format: "uuid", example: 'ledger-9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d'),
        new OA\Property(property: "transaction_id", type: "string", format: "uuid", example: 'tx-9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d'),
        new OA\Property(property: "source_wallet_id", type: "string", format: "uuid", example: 'wallet-parent-123'),
        new OA\Property(property: "destination_wallet_id", type: "string", format: "uuid", example: 'wallet-child-456'),
        new OA\Property(property: "amount", type: "number", format: "float", example: 5000.0),
        new OA\Property(property: "currency", type: "string", example: 'AOA'),
        new OA\Property(property: "type", type: "string", example: 'allowance'),
        new OA\Property(property: "description", type: "string", example: 'Pagamento de mesada semanal'),
        new OA\Property(property: "idempotency_key", type: "string", example: 'idem-9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d'),
        new OA\Property(property: "created_at", type: "string", format: "date-time", example: '2026-08-27T10:00:00Z')
    ]
)]
class LedgerEntry {}
