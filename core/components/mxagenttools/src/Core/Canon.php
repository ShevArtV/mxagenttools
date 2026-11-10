<?php

namespace MxAgentTools\Core;

/**
 * Канонический JSON снимков: ключи объектов отсортированы рекурсивно, списки — в своём порядке.
 * Один и тот же снимок даёт один и тот же hash независимо от порядка полей в базе и в журнале.
 */
final class Canon
{
    public static function json($value): string
    {
        return (string)json_encode(self::normalize($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public static function hash($value): string
    {
        return sha1(self::json($value));
    }

    public static function equal($a, $b): bool
    {
        return self::json($a) === self::json($b);
    }

    private static function normalize($value)
    {
        if (!is_array($value)) {
            return $value;
        }
        if (self::isList($value)) {
            return array_map([self::class, 'normalize'], $value);
        }
        ksort($value, SORT_STRING);
        foreach ($value as $k => $v) {
            $value[$k] = self::normalize($v);
        }
        return (object)$value;
    }

    public static function isList(array $value): bool
    {
        return $value === [] || array_keys($value) === range(0, count($value) - 1);
    }
}
