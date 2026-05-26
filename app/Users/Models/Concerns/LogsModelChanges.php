<?php

namespace App\Users\Models\Concerns;

use App\Users\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

trait LogsModelChanges
{
    protected static function bootLogsModelChanges(): void
    {
        static::created(function (Model $model): void {
            static::writeAuditLog($model, 'created', null, static::visibleAttributes($model));
        });

        static::updated(function (Model $model): void {
            $newValues = Arr::only(static::visibleAttributes($model), array_keys($model->getChanges()));
            unset($newValues['updated_at']);

            if ($newValues === []) {
                return;
            }

            $oldValues = [];

            foreach (array_keys($newValues) as $attribute) {
                $oldValues[$attribute] = $model->getOriginal($attribute);
            }

            static::writeAuditLog($model, 'updated', $oldValues, $newValues);
        });

        static::deleted(function (Model $model): void {
            static::writeAuditLog($model, 'deleted', static::visibleAttributes($model), null);
        });
    }

    protected static function writeAuditLog(Model $model, string $action, ?array $oldValues, ?array $newValues): void
    {
        AuditLog::query()->create([
            'model_type' => $model::class,
            'model_id' => $model->getKey(),
            'action' => $action,
            'changed_by' => auth()->id(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
        ]);
    }

    protected static function visibleAttributes(Model $model): array
    {
        return Arr::except($model->attributesToArray(), $model->getHidden());
    }
}
