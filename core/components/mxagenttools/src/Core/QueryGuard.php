<?php

namespace MxAgentTools\Core;

/**
 * Границы инструмента query (произвольная выборка xPDO): без MODX, чтобы проверяться тестами.
 * Запрещены классы пользователей, сессий и журналов менеджера, заказов и покупателей miniShop2, токенов и клиентов
 * mxApi, журнала и загрузок самого пакета. Код элементов (чанки, шаблоны, сниппеты, плагины) читается — query
 * только читает. Условия — только массивом xPDO с полями класса и известными
 * операторами: ключ условия xPDO подставляет в SQL, поэтому произвольная строка в ключе была бы инъекцией.
 */
final class QueryGuard
{
    const MAX_LIMIT = 200;
    const DEFAULT_LIMIT = 50;
    const CLASS_RE = '/^[A-Za-z][A-Za-z0-9_]{1,63}$/';
    const DENIED_CLASS_RE = '/^(modUser.*|modSession|modManagerLog|modActiveUser|msOrder.*|msCustomer.*|mxApi.*|mxat.*)$/i';
    const OPERATORS = ['=', '!=', '<>', '>', '<', '>=', '<=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN', 'IS', 'IS NOT'];
    const KEY_RE = '/^(?:(AND|OR):)?([A-Za-z_][A-Za-z0-9_]*)(?::([^:]+))?$/i';

    /** @var string[] классы, закрытые плагинами сайта (в нижнем регистре) */
    private static $denied = [];

    /** @param string[] $classes классы сайта, которые query не отдаёт (событие mxAgentToolsOnRegister) */
    public static function deny(array $classes): void
    {
        self::$denied = array_values(array_unique(array_merge(self::$denied, array_map('strtolower', array_map('strval', $classes)))));
    }

    public static function checkClass(string $class): void
    {
        if (!preg_match(self::CLASS_RE, $class)) {
            throw new Refusal("query: недопустимое имя класса {$class}");
        }
        if (preg_match(self::DENIED_CLASS_RE, $class) || in_array(strtolower($class), self::$denied, true)) {
            throw new Refusal("query: класс {$class} закрыт (пользователи, покупатели и заказы, сессии, журнал менеджера, "
                . 'токены API, журнал агента, классы, закрытые сайтом)');
        }
    }

    /**
     * Поля выборки: пусто — все поля класса, кроме полей с кодом
     * @param string[] $fields все поля класса
     */
    public static function select(string $class, array $fields, array $select): array
    {
        if (!$select) {
            return array_values($fields);
        }
        foreach ($select as $field) {
            self::field($class, $fields, (string)$field);
        }
        return array_values(array_unique(array_map('strval', $select)));
    }

    public static function where(string $class, array $fields, array $where, int $depth = 0): array
    {
        if ($depth > 3) {
            throw new Refusal('query: where — вложенность групп не больше 3');
        }
        foreach ($where as $key => $value) {
            if (is_int($key)) {
                if (!is_array($value)) {
                    throw new Refusal('query: where — строковые условия (SQL) не принимаются, только {"поле:оператор": значение}');
                }
                self::where($class, $fields, $value, $depth + 1);
                continue;
            }
            if (!preg_match(self::KEY_RE, $key, $m)) {
                throw new Refusal("query: where — недопустимый ключ «{$key}»");
            }
            self::field($class, $fields, $m[2], true);
            $op = strtoupper(trim($m[3] ?? '='));
            if (!in_array($op, self::OPERATORS, true)) {
                throw new Refusal("query: where — оператор «{$op}» не поддерживается, есть " . implode(' ', self::OPERATORS));
            }
            $list = in_array($op, ['IN', 'NOT IN'], true);
            if ($list !== is_array($value)) {
                throw new Refusal("query: where «{$key}» — " . ($list ? 'нужен список значений' : 'нужно одно значение'));
            }
            foreach ($list ? $value : [$value] as $v) {
                if ($v !== null && !is_scalar($v)) {
                    throw new Refusal("query: where «{$key}» — значения только строки и числа");
                }
            }
        }
        return $where;
    }

    /** sort: {"поле": "ASC|DESC"} → [[поле, направление]] */
    public static function sort(string $class, array $fields, array $sort): array
    {
        $out = [];
        foreach ($sort as $field => $dir) {
            self::field($class, $fields, (string)$field, true);
            $dir = strtoupper((string)$dir);
            if (!in_array($dir, ['ASC', 'DESC'], true)) {
                throw new Refusal("query: sort {$field} — ASC или DESC");
            }
            $out[] = [(string)$field, $dir];
        }
        return $out;
    }

    public static function limit($limit): int
    {
        $limit = $limit === null || $limit === '' ? self::DEFAULT_LIMIT : (int)$limit;
        if ($limit < 1 || $limit > self::MAX_LIMIT) {
            throw new Refusal('query: limit от 1 до ' . self::MAX_LIMIT);
        }
        return $limit;
    }

    /** $filter — поле в where/sort: значение настройки там подбиралось бы перебором, поэтому value закрыт */
    private static function field(string $class, array $fields, string $field, bool $filter = false): void
    {
        if (!in_array($field, $fields, true)) {
            throw new Refusal("query: у {$class} нет поля «{$field}»");
        }
        if ($filter && $field === 'value' && Secrets::isValueClass($class)) {
            throw new Refusal("query: {$class}.value — только в выборке, не в where и sort (значения секретов скрыты); "
                . 'поиск по значению настроек — setting.get');
        }
    }
}
