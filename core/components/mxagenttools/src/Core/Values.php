<?php

namespace MxAgentTools\Core;

/** Значения снимков: строки, как их хранит база (bool → '1'/'0', null → ''), списки — списками строк */
final class Values
{
    public static function str($value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_array($value)) {
            return Canon::json($value);
        }
        return $value === null ? '' : (string)$value;
    }

    /** Список строк: «a» → ['a'], null и '' → [] */
    public static function strList($value): array
    {
        if ($value === null || $value === '') {
            return [];
        }
        return array_values(array_map([self::class, 'str'], is_array($value) ? $value : [$value]));
    }

    /** Словарь [ключ => строка] */
    public static function strMap(array $values): array
    {
        return array_map([self::class, 'str'], $values);
    }

    /** Целое > 0 из аргумента или отказ */
    public static function id(array $args, string $key): int
    {
        $v = $args[$key] ?? null;
        if (!(is_int($v) || is_string($v) && ctype_digit($v)) || (int)$v <= 0) {
            throw new Refusal("{$key}: нужен id (целое больше 0)");
        }
        return (int)$v;
    }

    /** Необязательный JSON-объект аргумента (словарь) */
    public static function map(array $args, string $key): array
    {
        $v = $args[$key] ?? [];
        if (!is_array($v) || $v !== [] && Canon::isList($v)) {
            throw new Refusal("{$key}: нужен объект {ключ: значение}");
        }
        return $v;
    }

    /** Необязательный список аргумента */
    public static function items(array $args, string $key): array
    {
        $v = $args[$key] ?? [];
        if (!is_array($v) || !Canon::isList($v)) {
            throw new Refusal("{$key}: нужен список");
        }
        return $v;
    }

    /** Строка аргумента; $required — пустая даёт отказ */
    public static function text(array $args, string $key, bool $required = false): string
    {
        $v = $args[$key] ?? '';
        if (!is_scalar($v)) {
            throw new Refusal("{$key}: нужна строка");
        }
        $v = trim((string)$v);
        if ($required && $v === '') {
            throw new Refusal("{$key}: обязателен");
        }
        return $v;
    }
}
