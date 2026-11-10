<?php

namespace MxAgentTools\Tool\System;

use MxAgentTools\Core\QueryGuard;
use MxAgentTools\Core\Refusal;
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
        . 'limit до 200 (по умолчанию 50), offset. Закрыты классы пользователей, сессий и журнала менеджера; код '
        . 'элементов (content шаблонов, snippet чанков и сниппетов, plugincode плагинов) не отдаётся.';

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
        $total = $this->modx->getCount($class, clone $q);
        $q->select($this->modx->getSelectColumns($class, $q->getAlias(), '', $select));
        foreach ($sort as [$field, $dir]) {
            $q->sortby($this->modx->escape($q->getAlias()) . '.' . $this->modx->escape($field), $dir);
        }
        $q->limit($limit, max(0, (int)($args['offset'] ?? 0)));
        $rows = [];
        if ($q->prepare() && $q->stmt->execute()) {
            $rows = $q->stmt->fetchAll(\PDO::FETCH_ASSOC);
        }
        return ['class' => $class, 'total' => $total, 'rows' => $rows];
    }
}
