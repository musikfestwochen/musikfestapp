<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Class BinaryHexCast
 *
 * This class is used to cast binary data to hexadecimal format and vice versa.
 * It implements the CastsAttributes interface from Laravel.
 *
 * @implements CastsAttributes<string, string>
 */
class BinaryHexCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value !== null ? bin2hex($value) : null;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        $bin = $value !== null ? hex2bin($value) : null;

        return $bin === false ? null : $bin; // @pest-mutate-ignore
    }
}
