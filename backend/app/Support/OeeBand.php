<?php

namespace App\Support;

class OeeBand
{
    public const RED_BELOW = 65.0;
    public const GREEN_AT_LEAST = 85.0;

    public static function colorFor(?float $value): string
    {
        if ($value === null) {
            return 'gray';
        }

        if ($value >= self::GREEN_AT_LEAST) {
            return 'green';
        }

        if ($value >= self::RED_BELOW) {
            return 'yellow';
        }

        return 'red';
    }

    public static function textClass(?float $value): string
    {
        return match (self::colorFor($value)) {
            'green' => 'text-green-600 dark:text-green-400',
            'yellow' => 'text-yellow-600 dark:text-yellow-400',
            'red' => 'text-red-600 dark:text-red-400',
            default => 'text-gray-500 dark:text-gray-400',
        };
    }

    public static function bgClass(?float $value): string
    {
        return match (self::colorFor($value)) {
            'green' => 'bg-green-500',
            'yellow' => 'bg-yellow-500',
            'red' => 'bg-red-500',
            default => 'bg-gray-300',
        };
    }

    public static function cardClass(?float $value): string
    {
        return match (self::colorFor($value)) {
            'green' => 'border-green-200 bg-green-50 dark:bg-green-900/20 dark:border-green-800',
            'yellow' => 'border-yellow-200 bg-yellow-50 dark:bg-yellow-900/20 dark:border-yellow-800',
            'red' => 'border-red-200 bg-red-50 dark:bg-red-900/20 dark:border-red-800',
            default => 'border-gray-200 bg-gray-50 dark:bg-slate-700 dark:border-slate-600',
        };
    }
}
