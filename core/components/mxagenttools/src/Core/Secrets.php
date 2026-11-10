<?php

namespace MxAgentTools\Core;

/**
 * Значения настроек, ключ которых похож на секрет (пароли, токены, ключи API, соль), наружу уходят маской:
 * сама настройка видна, значение — нет. Запись таких настроек не запрещена — закрыто только чтение.
 * Без MODX, чтобы проверяться тестами. Маскируются ответы чтения (setting.get, clientconfig.get, query по
 * классам настроек) и снимки «setting:…» / «clientconfig:…» в ответах записи, отката и журнала; hash плана
 * считается по настоящим значениям до маски.
 */
final class Secrets
{
    const MASK = '***';
    const KEY_RE = '/pass|secret|token|key|private|salt|hash/i';
    /** Виды снимков, где value — значение настройки (Entity\Setting, Entity\ClientConfig) */
    const ENTITY_TYPES = ['setting', 'clientconfig'];
    /** Классы xPDO с парой key/value настроек (query): значение маскируется, value в where/sort не участвует */
    const SETTING_CLASSES = ['modsystemsetting', 'modcontextsetting', 'modusersetting', 'cgsetting'];
    /** Значения настроек ClientConfig по контекстам: ключ — в cgSetting, value в where/sort не участвует */
    const VALUE_CLASSES = ['cgcontextvalue'];
    /** Поля снимков и ответов со значениями — то, что закрывается маской */
    const VALUE_FIELDS = ['value', 'contexts'];

    public static function isSecretKey(string $key): bool
    {
        return (bool)preg_match(self::KEY_RE, $key);
    }

    /** Пустое так и остаётся пустым: «не задано» — не секрет */
    public static function mask($value)
    {
        if (is_array($value)) {
            return array_map([self::class, 'mask'], $value);
        }
        return $value === null || $value === '' ? $value : self::MASK;
    }

    /** Строка настройки (key + value [+ contexts]) — значение маской, если ключ похож на секрет */
    public static function row(array $row, string $keyField = 'key'): array
    {
        if (!self::isSecretKey((string)($row[$keyField] ?? ''))) {
            return $row;
        }
        foreach (self::VALUE_FIELDS as $field) {
            if (array_key_exists($field, $row)) {
                $row[$field] = self::mask($row[$field]);
            }
        }
        return $row;
    }

    /** Класс query, у которого значение настройки (секретность — по ключу настройки) */
    public static function isValueClass(string $class): bool
    {
        $class = strtolower($class);
        return in_array($class, self::SETTING_CLASSES, true) || in_array($class, self::VALUE_CLASSES, true);
    }

    /**
     * Ответ инструмента/журнала: снимки «setting:<ключ-секрет>» и «clientconfig:<ключ-секрет>» в любых картах
     * снимков (before, after, predicted, current…) — value маской; строки diff/divergence/unexpected про такой
     * объект — «значение скрыто».
     */
    public static function output(array $out): array
    {
        foreach ($out as $name => $value) {
            if (!is_array($value)) {
                continue;
            }
            if (self::isLineList($value)) {
                $out[$name] = array_map([self::class, 'line'], $value);
            } elseif (self::isSnapshotMap($value)) {
                foreach ($value as $key => $snapshot) {
                    if (is_array($snapshot) && self::secretRef((string)$key)) {
                        $value[$key] = self::row($snapshot + ['key' => self::refId((string)$key)]);
                        if (!array_key_exists('key', $snapshot)) {
                            unset($value[$key]['key']);
                        }
                    }
                }
                $out[$name] = $value;
            } elseif (!self::isList($value)) {
                // параметры записи настройки ({key, value}) — как строка настройки
                $out[$name] = self::output(is_string($value['key'] ?? null) ? self::row($value) : $value);
            } else {
                $out[$name] = array_map(function ($item) {
                    return is_array($item) ? self::output($item) : $item;
                }, $value);
            }
        }
        return $out;
    }

    /** Строка «setting:<ключ> value: «…» → «…»» про секрет — без значений */
    public static function line(string $line): string
    {
        if (!preg_match('/^([a-z][a-z0-9_]*:[^\s]+)(?: ([^:]+))?:/', $line, $m) || !self::secretRef($m[1])) {
            return $line;
        }
        $field = $m[2] ?? '';
        if ($field !== '' && preg_match('/^(value|contexts)(\.|\[|$)/', $field)) {
            return "{$m[1]} {$field}: значение скрыто (секрет)";
        }
        return $line;
    }

    private static function secretRef(string $key): bool
    {
        $pos = strpos($key, ':');
        return $pos !== false && in_array(substr($key, 0, $pos), self::ENTITY_TYPES, true)
            && self::isSecretKey(self::refId($key));
    }

    private static function refId(string $key): string
    {
        return (string)substr($key, strpos($key, ':') + 1);
    }

    private static function isSnapshotMap(array $value): bool
    {
        if (!$value || self::isList($value)) {
            return false;
        }
        foreach ($value as $key => $snapshot) {
            if (!preg_match('/^[a-z][a-z0-9_]*:/', (string)$key) || ($snapshot !== null && !is_array($snapshot))) {
                return false;
            }
        }
        return true;
    }

    private static function isLineList(array $value): bool
    {
        if (!$value || !self::isList($value)) {
            return false;
        }
        foreach ($value as $item) {
            if (!is_string($item)) {
                return false;
            }
        }
        return true;
    }

    private static function isList(array $value): bool
    {
        return $value === [] || array_keys($value) === range(0, count($value) - 1);
    }
}
