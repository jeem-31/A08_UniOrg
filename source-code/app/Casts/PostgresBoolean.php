<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class PostgresBoolean implements CastsAttributes
{
    /**
     * Cast the given value from the database.
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?bool
    {
        if ($value === null) {
            return null;
        }
        if (is_bool($value)) {
            return $value;
        }
        if ($value instanceof \Illuminate\Contracts\Database\Query\Expression) {
            $value = (string) $value->getValue($model->getConnection()->getQueryGrammar());
        }
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Prepare the given value for storage in the database.
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null) {
            return null;
        }
        if ($value instanceof \Illuminate\Contracts\Database\Query\Expression) {
            return $value;
        }

        $boolean = (bool) filter_var($value, FILTER_VALIDATE_BOOLEAN);

        if ($model->getConnection()->getDriverName() === 'pgsql') {
            return $boolean ? \Illuminate\Support\Facades\DB::raw('TRUE') : \Illuminate\Support\Facades\DB::raw('FALSE');
        }

        return $boolean;
    }
}
