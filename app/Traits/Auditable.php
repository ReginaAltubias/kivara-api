<?php

namespace App\Traits;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

trait Auditable
{
    /**
     * Campos sensíveis que nunca devem ir para o audit log.
     *
     * @var list<string>
     */
    protected static array $auditHidden = [
        'password',
        'remember_token',
        'pairing_code',
        'pin',
    ];

    /**
     * Regista listeners de auditoria no modelo.
     */
    public static function bootAuditable(): void
    {
        static::created(function (Model $model) {
            static::writeAuditLog($model, 'created');
        });

        static::updated(function (Model $model) {
            static::writeAuditLog($model, 'updated');
        });

        static::deleted(function (Model $model) {
            static::writeAuditLog($model, 'deleted');
        });
    }

    /**
     * Persiste um registo de auditoria para o evento.
     */
    protected static function writeAuditLog(Model $model, string $event): void
    {
        $changes = static::auditPayload($model, $event);

        if ($event === 'updated' && empty($changes['old_values']) && empty($changes['new_values'])) {
            return;
        }

        AuditLog::create([
            'user_id' => auth()->id(),
            'event' => $event,
            'model_type' => $model::class,
            'model_id' => $model->getKey(),
            'old_values' => $changes['old_values'],
            'new_values' => $changes['new_values'],
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }

    /**
     * Constrói old/new values sem campos sensíveis.
     *
     * @return array{old_values: array<string, mixed>|null, new_values: array<string, mixed>|null}
     */
    protected static function auditPayload(Model $model, string $event): array
    {
        $hidden = property_exists($model, 'auditExclude')
            ? array_merge(static::$auditHidden, $model->auditExclude)
            : static::$auditHidden;

        return match ($event) {
            'created' => [
                'old_values' => null,
                'new_values' => Arr::except($model->getAttributes(), $hidden),
            ],
            'updated' => [
                'old_values' => Arr::except(
                    Arr::only($model->getOriginal(), array_keys($model->getChanges())),
                    $hidden
                ),
                'new_values' => Arr::except($model->getChanges(), $hidden),
            ],
            'deleted' => [
                'old_values' => Arr::except($model->getOriginal(), $hidden),
                'new_values' => null,
            ],
            default => [
                'old_values' => null,
                'new_values' => null,
            ],
        };
    }
}
