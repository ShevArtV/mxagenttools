<?php

namespace MxAgentTools\Modx;

use MxAgentTools\Core\Refusal;

/**
 * Зафиксированные выборки пакетных правок: id объектов по фильтру — в таблицу mxagenttools_selection одним
 * INSERT … SELECT, без загрузки списка в PHP. Порция page — строки выборки по порядку вставки (id таблицы), поэтому
 * смена шаблона или родителя между порциями выборку не сдвигает. Живут mxagenttools.selection_ttl секунд; старые
 * удаляются при создании новой.
 */
class Selections
{
    /** @var \modX */
    private $modx;
    /** @var int */
    private $ttl;

    public function __construct(\modX $modx, int $ttl)
    {
        $this->modx = $modx;
        $this->ttl = max(60, $ttl);
    }

    /** @param \xPDOQuery $ids запрос, выбирающий ровно одну колонку — id объекта */
    public function create(\xPDOQuery $ids): array
    {
        $now = time();
        $this->modx->removeCollection('mxatSelection', ['createdon:<' => $now - $this->ttl]);
        $selection = bin2hex(random_bytes(16));
        if (!$ids->prepare()) {
            throw new \RuntimeException('выборка не собралась');
        }
        $table = $this->modx->getTableName('mxatSelection');
        $sql = "INSERT INTO {$table} (`selection`, `object_id`, `createdon`) SELECT " . $this->modx->quote($selection)
            . ", `ids`.`id`, {$now} FROM (" . $ids->toSQL() . ') AS `ids`';
        if ($this->modx->exec($sql) === false) {
            $info = $this->modx->errorInfo();
            throw new \RuntimeException('выборка не записалась: ' . ($info[2] ?? ''));
        }
        return ['selection' => $selection, 'total' => $this->total($selection), 'expires' => date('c', $now + $this->ttl)];
    }

    public function total(string $selection): int
    {
        return (int)$this->modx->getCount('mxatSelection', ['selection' => $selection]);
    }

    /** id объектов порции page (с 0) по size штук; нет выборки — отказ */
    public function page(string $selection, int $page, int $size): array
    {
        if (!preg_match('/^[0-9a-f]{32}$/', $selection) || ($total = $this->total($selection)) === 0) {
            throw new Refusal('выборки нет или она устарела (живёт ' . round($this->ttl / 3600) . ' ч) — соберите заново');
        }
        if ($page < 0 || $page * $size >= $total) {
            throw new Refusal("порции {$page} нет: в выборке {$total} объектов, порций " . (int)ceil($total / $size));
        }
        $q = $this->modx->newQuery('mxatSelection', ['selection' => $selection]);
        $q->select('object_id');
        $q->sortby('id', 'ASC');
        $q->limit($size, $page * $size);
        return ($q->prepare() && $q->stmt->execute()) ? array_map('intval', $q->stmt->fetchAll(\PDO::FETCH_COLUMN)) : [];
    }
}
