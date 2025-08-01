<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class BinaryHexCast implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes): ?string
    {
        return $value !== null ? bin2hex($value) : null;
    }

    public function set($model, string $key, $value, array $attributes): ?string
    {
        $bin = $value !== null ? hex2bin($value) : null;

        return $bin === false ? null : $bin;
    }
}
