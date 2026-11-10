<?php

namespace MxAgentTools\Tool\System;

use MxAgentTools\Core\QueryGuard;
use MxAgentTools\Core\Refusal;
use MxAgentTools\Core\Secrets;
use MxAgentTools\Core\Values;
use MxAgentTools\Tool\AbstractTool;
use MxAgentTools\Tool\ReadToolInterface;

/** Произвольная выборка xPDO в границах QueryGuard; значения — сырые строки базы */
class Query extends AbstractTool implements ReadToolInterface
{
    const NAME = 'query';
    const TITLE = 'Выборка xPDO';
    const PATH = '/agent/query';
    const DESCRIPTION = 'Чтение любой таблицы модели по классу xPDO, когда готового инструмента нет: class, select (поля; '
        . 'пусто — все), where в формате xPDO {"поле:оператор": значение} (операторы = != <> > < >= <= LIKE NOT LIKE IN '
        . 'NOT IN IS IS NOT; OR: в начале ключа — «или»; вложенные группы — списком объектов), sort {"поле": "ASC|DESC"}, '
        . 'limit до 200 (по умолчанию 50), offset. Закрыты классы пользователей, покупателей и заказов miniShop2 (msOrder*, '
        . 'msCustomer*), сессий, журнала менеджера, токенов mxApi и журнала агента. Код элементов (modChunk, modTemplate, '
        . 'modSnippet, modPlugin, modTemplateVar) читается вместе с полями кода — только чтение. У настроек (modSystemSetting, modContextSetting, '
        . 'cgSetting, cgContextValue) значение с ключом, похожим на секрет (pass, secret, token, key, private, salt, hash), — '
        . '«***», value не участвует в where и sort.';

    /** @var \modX */
    private $modx;

    public function __construct(\modX $modx)
    {
        $this->modx = $modx;
    }

    protected function args(): array
    {
        return self::obj([
            'class' => self::str('класс xPDO', 'msOrder'),
            'select' => self::ids('поля', ['id', 'num', 'cost']),
            'where' => self::map('условия xPDO', ['status' => 2, 'cost:>' => 1000]),
            'sort' => self::map('сортировка', ['id' => 'DESC']),
            'limit' => self::int('до 200', 20),
            'offset' => self::int('сдвиг', 0),
        ], ['class']);
    }

    public function read(array $args): array
    {
        $class = Values::text($args, 'class', true);
        QueryGuard::checkClass($class);
        if (!$this->modx->loadClass($class)) {
            throw new Refusal("класса {$class} нет в модели (пакет не установлен или не загружен)");
        }
        $fields = array_keys((array)$this->modx->getFields($class));
        $select = QueryGuard::select($class, $fields, array_map('strval', Values::items($args, 'select')));
        $where = QueryGuard::where($class, $fields, Values::map($args, 'where'));
        $sort = QueryGuard::sort($class, $fields, Values::map($args, 'sort'));
        $limit = QueryGuard::limit($args['limit'] ?? null);
        $q = $this->modx->newQuery($class);
        if ($where) {
            $q->where($where);
        }
        // Наследник modResource (msProduct, msCategory…) — сама xPDO class_key не фильтрует, и msProduct отдавал категории
        if (in_array('class_key', $fields, true) && strcasecmp($class, 'modResource') !== 0) {
            $q->where(['class_key' => $this->modx->loadClass($class) ?: $class]);
        }
        $total = $this->modx->getCount($class, clone $q);
        $secret = Secrets::isValueClass($class) && in_array('value', $select, true);
        $keyField = strtolower($class) === 'cgcontextvalue' ? 'setting' : 'key';
        $query = $secret && !in_array($keyField, $select, true) ? array_merge($select, [$keyField]) : $select;
        $q->select($this->modx->getSelectColumns($class, $q->getAlias(), '', $query));
        foreach ($sort as [$field, $dir]) {
            $q->sortby($this->modx->escape($q->getAlias()) . '.' . $this->modx->escape($field), $dir);
        }
        $q->limit($limit, max(0, (int)($args['offset'] ?? 0)));
        $rows = [];
        if ($q->prepare() && $q->stmt->execute()) {
            $rows = $q->stmt->fetchAll(\PDO::FETCH_ASSOC);
        }
        if ($secret) {
            $rows = $this->mask($rows, $keyField, $select);
        }
        return ['class' => $class, 'total' => $total, 'rows' => $rows];
    }

    /** Значения настроек-секретов — маской; ключ, добавленный только ради проверки, из строки убирается */
    private function mask(array $rows, string $keyField, array $select): array
    {
        $keys = [];
        if ($keyField === 'setting') {
            $ids = array_unique(array_map('intval', array_column($rows, 'setting')));
            if ($ids) {
                foreach ($this->modx->getIterator('cgSetting', ['id:IN' => $ids], false) as $s) {
                    $keys[(int)$s->get('id')] = (string)$s->get('key');
                }
            }
        }
        foreach ($rows as $i => $row) {
            $key = $keyField === 'setting' ? ($keys[(int)$row['setting']] ?? '') : (string)$row['key'];
            if (array_key_exists('value', $row) && Secrets::isSecretKey($key)) {
                $row['value'] = Secrets::mask($row['value']);
            }
            if (!in_array($keyField, $select, true)) {
                unset($row[$keyField]);
            }
            $rows[$i] = $row;
        }
        return $rows;
    }
}
