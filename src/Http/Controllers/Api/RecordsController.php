<?php

namespace OneLearningCommunity\LaravelModelExplorer\Http\Controllers\Api;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\ModelInfo\ModelInfo;

class RecordsController
{
    public function show(string $model, Request $request): JsonResponse
    {
        $className = base64_decode(strtr($model, '-_', '+/'));

        if (! $className || ! class_exists($className)) {
            return response()->json(['message' => 'Model not found.'], 404);
        }

        $value = $request->query('value');

        if ($value === null || $value === '') {
            return response()->json(['message' => 'The value field is required.'], 422);
        }

        $field = $request->query('field') ?: (new $className())->getKeyName();

        return $this->withinSafeRead(function () use ($className, $field, $value): JsonResponse {
            $record = $className::query()->where($field, $value)->first();

            if (! $record) {
                return response()->json(['message' => 'Record not found.'], 404);
            }

            return response()->json($this->buildRecordPayload($record));
        });
    }

    public function relation(string $model, string $relation, Request $request): JsonResponse
    {
        $className = base64_decode(strtr($model, '-_', '+/'));

        if (! $className || ! class_exists($className)) {
            return response()->json(['message' => 'Model not found.'], 404);
        }

        $recordKey = $request->query('record_key');

        if ($recordKey === null || $recordKey === '') {
            return response()->json(['message' => 'The record_key field is required.'], 422);
        }

        return $this->withinSafeRead(function () use ($className, $recordKey, $relation, $request): JsonResponse {
            $record = $className::find($recordKey);

            if (! $record) {
                return response()->json(['message' => 'Record not found.'], 404);
            }

            if (! method_exists($record, $relation)) {
                return response()->json(['message' => 'Relation not found.'], 404);
            }

            try {
                $relInstance = $record->{$relation}();
            } catch (\Throwable) {
                return response()->json(['message' => 'Relation not found.'], 404);
            }

            if (! $relInstance instanceof Relation) {
                return response()->json(['message' => 'Relation not found.'], 404);
            }

            if ($this->isToOneRelation($relInstance)) {
                $related = $record->{$relation};

                return response()->json([
                    'type' => 'one',
                    'record' => $related ? $this->buildRecordPayload($related) : null,
                ]);
            }

            $page = max(1, (int) $request->query('page', 1));
            $paginator = $relInstance->paginate(15, ['*'], 'page', $page);

            return response()->json([
                'type' => 'many',
                'records' => collect($paginator->items())->map(fn (Model $r) => $this->buildRecordPayload($r))->values(),
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
            ]);
        });
    }

    public function attributes(string $model, Request $request): JsonResponse
    {
        $className = base64_decode(strtr($model, '-_', '+/'));

        if (! $className || ! class_exists($className)) {
            return response()->json(['message' => 'Model not found.'], 404);
        }

        $recordKey = $request->query('record_key');

        if ($recordKey === null || $recordKey === '') {
            return response()->json(['message' => 'The record_key field is required.'], 422);
        }

        $names = $request->query('names', []);

        return $this->withinSafeRead(function () use ($className, $recordKey, $names): JsonResponse {
            $record = $className::find($recordKey);

            if (! $record) {
                return response()->json(['message' => 'Record not found.'], 404);
            }

            $known = $this->knownAttributes($record);

            // If no names requested, default to all virtual attributes.
            if (empty($names)) {
                $names = array_diff($known, array_keys($record->getAttributes()));
            }

            $results = [];

            foreach ($names as $name) {
                if (! in_array($name, $known, strict: true)) {
                    continue;
                }

                try {
                    $results[$name] = ['value' => $this->serializeAccessorValue($record->{$name}), 'error' => null];
                } catch (\Throwable $e) {
                    $results[$name] = ['value' => null, 'error' => $e->getMessage()];
                }
            }

            return response()->json($results);
        });
    }

    public function attribute(string $model, string $attribute, Request $request): JsonResponse
    {
        $className = base64_decode(strtr($model, '-_', '+/'));

        if (! $className || ! class_exists($className)) {
            return response()->json(['message' => 'Model not found.'], 404);
        }

        $recordKey = $request->query('record_key');

        if ($recordKey === null || $recordKey === '') {
            return response()->json(['message' => 'The record_key field is required.'], 422);
        }

        return $this->withinSafeRead(function () use ($className, $recordKey, $attribute): JsonResponse {
            $record = $className::find($recordKey);

            if (! $record) {
                return response()->json(['message' => 'Record not found.'], 404);
            }

            // Validate the attribute is a known column or virtual attribute,
            // not an arbitrary method name.
            $known = $this->knownAttributes($record);

            if (! in_array($attribute, $known, strict: true)) {
                return response()->json(['message' => 'Attribute not found.'], 404);
            }

            try {
                $value = $this->serializeAccessorValue($record->{$attribute});
            } catch (\Throwable $e) {
                return response()->json(['name' => $attribute, 'value' => null, 'error' => $e->getMessage()]);
            }

            return response()->json(['name' => $attribute, 'value' => $value]);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function buildRecordPayload(Model $record): array
    {
        return [
            'key_field' => $record->getKeyName(),
            'key_value' => $record->getKey(),
            'model_class' => get_class($record),
            'short_name' => class_basename($record),
            'attributes' => $record->getAttributes(),
        ];
    }

    /**
     * Executes a callback with model events disabled and inside a transaction that always
     * rolls back. This prevents accidental writes from observers or accessor side effects
     * from persisting to the database.
     *
     * Note: non-database side effects (HTTP calls, cache writes, queue pushes) are not
     * prevented. If an accessor makes external calls, those will still execute.
     *
     * Note: only covers the model's default database connection. If your models span
     * multiple connections, writes on other connections are not rolled back.
     */
    private function withinSafeRead(callable $callback): mixed
    {
        DB::beginTransaction();

        try {
            return Model::withoutEvents($callback);
        } finally {
            DB::rollBack();
        }
    }

    /**
     * Normalises an accessor return value for the API response.
     *
     * If the value is a single Eloquent Model it is wrapped as a to-one record payload.
     * If the value is a Collection it is wrapped as a to-many record payload (capped at 15
     * items to avoid oversized responses; the full count is included so the UI can show it).
     * All other values are returned unchanged.
     */
    private function serializeAccessorValue(mixed $value): mixed
    {
        if ($value instanceof Model) {
            return ['type' => 'one', 'record' => $this->buildRecordPayload($value)];
        }

        if ($value instanceof Collection && $value->first() instanceof Model) {
            $total = $value->count();
            $records = $value
                ->take(15)
                ->filter(fn ($item) => $item instanceof Model)
                ->map(fn (Model $m) => $this->buildRecordPayload($m))
                ->values();

            return ['type' => 'many', 'records' => $records, 'total' => $total];
        }

        return $value;
    }

    /**
     * Names of all known resolvable attributes for the given record: raw columns plus
     * virtual attributes as defined by ModelInfo (consistent with ModelData/ModelInspector).
     *
     * @return list<string>
     */
    private function knownAttributes(Model $record): array
    {
        try {
            $virtualNames = ModelInfo::forModel(get_class($record))
                ->attributes
                ->filter(fn ($attr) => $attr->virtual)
                ->pluck('name')
                ->all();
        } catch (\Throwable) {
            $virtualNames = $record->getAppends();
        }

        return array_merge(array_keys($record->getAttributes()), $virtualNames);
    }

    private function isToOneRelation(Relation $relation): bool
    {
        return $relation instanceof HasOne
            || $relation instanceof HasOneThrough
            || $relation instanceof BelongsTo
            || $relation instanceof MorphOne
            || $relation instanceof MorphTo;
    }
}
