<?php

declare(strict_types=1);

namespace App\Enums\StageSafety;

enum SensorType: string
{
    case BroadweighBwWss = 'BW-WSS';

    public function manufacturer(): string
    {
        return match ($this) {
            self::BroadweighBwWss => 'broadweigh',
        };
    }

    public function model(): string
    {
        return $this->value;
    }

    public function displayName(): string
    {
        return match ($this) {
            self::BroadweighBwWss => 'BroadWeigh BW-WSS',
        };
    }

    public static function fromPair(string $manufacturer, string $model): ?self
    {
        foreach (self::cases() as $sensorType) {
            if ($sensorType->manufacturer() === $manufacturer && $sensorType->model() === $model) {
                return $sensorType;
            }
        }

        return null;
    }
}
