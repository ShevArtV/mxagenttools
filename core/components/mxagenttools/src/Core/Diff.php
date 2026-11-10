<?php

namespace MxAgentTools\Core;

/**
 * Отличия снимков для человека: строки «объект поле: было → станет». Снимок раскладывается в пути
 * (fields.pagetitle, tvs.color, files[2].name); списки простых значений сравниваются целиком.
 * Длинные значения (content) обрезаются — полный текст остаётся в before/after ответа.
 */
final class Diff
{
    const MAX = 200;

    /**
     * @param array $before «type:id» => снимок|null
     * @param array $after  «type:id» => снимок|null
     * @return string[]
     */
    public static function lines(array $before, array $after): array
    {
        $out = [];
        foreach (self::keys($before, $after) as $key) {
            $b = $before[$key] ?? null;
            $a = $after[$key] ?? null;
            if ($b === null && $a === null) {
                continue;
            }
            if ($a === null) {
                $out[] = "{$key}: удаляется";
                continue;
            }
            if ($b === null) {
                $out[] = "{$key}: создаётся";
                foreach (self::flatten($a) as $path => $value) {
                    if ($value !== null && $value !== '' && $value !== '[]') {
                        $out[] = "{$key} {$path}: " . self::show($value);
                    }
                }
                continue;
            }
            $fb = self::flatten($b);
            $fa = self::flatten($a);
            foreach (self::keys($fb, $fa) as $path) {
                $vb = $fb[$path] ?? null;
                $va = $fa[$path] ?? null;
                if ($vb !== $va) {
                    $out[] = "{$key} {$path}: " . self::show($vb) . ' → ' . self::show($va);
                }
            }
        }
        return $out;
    }

    /**
     * Расхождение при откате: что записал бот, что сейчас, что станет после отката.
     * @return string[]
     */
    public static function threeWay(array $bot, array $current, array $target): array
    {
        $out = [];
        foreach (self::keys($bot, $current, $target) as $key) {
            $fb = isset($bot[$key]) ? self::flatten($bot[$key]) : [];
            $fc = isset($current[$key]) ? self::flatten($current[$key]) : [];
            $ft = isset($target[$key]) ? self::flatten($target[$key]) : [];
            $paths = array_values(array_unique(array_merge(array_keys($fb), array_keys($fc), array_keys($ft))));
            foreach ($paths as $path) {
                $vb = $fb[$path] ?? null;
                $vc = $fc[$path] ?? null;
                $vt = $ft[$path] ?? null;
                if ($vb !== $vc || $vc !== $vt) {
                    $out[] = "{$key} {$path}: у бота " . self::show($vb) . ' → сейчас ' . self::show($vc)
                        . ' → станет ' . self::show($vt);
                }
            }
            if (!isset($target[$key]) && isset($current[$key])) {
                $out[] = "{$key}: удаляется";
            }
        }
        return $out;
    }

    /** Снимок → [путь => строковое представление] */
    public static function flatten(array $value, string $prefix = ''): array
    {
        $out = [];
        if (Canon::isList($value) && !array_filter($value, 'is_array')) {
            $out[$prefix === '' ? '.' : $prefix] = Canon::json($value);
            return $out;
        }
        foreach ($value as $k => $v) {
            $path = is_int($k) && Canon::isList($value) ? "{$prefix}[{$k}]" : ($prefix === '' ? (string)$k : "{$prefix}.{$k}");
            if (is_array($v)) {
                $out += $v === [] ? [$path => '[]'] : self::flatten($v, $path);
            } else {
                $out[$path] = $v === null ? null : (is_bool($v) ? ($v ? '1' : '0') : (string)$v);
            }
        }
        return $out;
    }

    private static function show(?string $value): string
    {
        if ($value === null) {
            return 'нет';
        }
        $len = mb_strlen($value);
        if ($len > self::MAX) {
            return '«' . mb_substr($value, 0, self::MAX) . "…» ({$len} симв.)";
        }
        return "«{$value}»";
    }

    /** Ключи массивов в порядке появления */
    private static function keys(array ...$arrays): array
    {
        $keys = [];
        foreach ($arrays as $array) {
            foreach (array_keys($array) as $k) {
                $keys[(string)$k] = true;
            }
        }
        return array_map('strval', array_keys($keys));
    }
}
