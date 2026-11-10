<?php

namespace MxAgentTools\Tool\Batch;

use MxAgentTools\Modx\ResourceFilter;
use MxAgentTools\Modx\Selections;
use MxAgentTools\Tool\AbstractTool;
use MxAgentTools\Tool\ReadToolInterface;

/**
 * Выборка для пакетной правки: фильтр → id в таблицу выборок на сервере (без потолка объёма). Ответ: selection,
 * total, pages (порций по batch_limit), page_size, first — первые 20 id для показа, expires.
 */
abstract class SelectTool extends AbstractTool implements ReadToolInterface
{
    const PRODUCTS = false;

    /** @var \modX */
    private $modx;
    /** @var Selections */
    private $selections;
    /** @var int */
    private $limit;

    public function __construct(\modX $modx, Selections $selections, int $limit)
    {
        $this->modx = $modx;
        $this->selections = $selections;
        $this->limit = max(1, $limit);
    }

    protected function args(): array
    {
        return self::obj(ResourceFilter::args(static::PRODUCTS));
    }

    public function read(array $args): array
    {
        $out = $this->selections->create(ResourceFilter::query($this->modx, $args, static::PRODUCTS));
        $out['page_size'] = $this->limit;
        $out['pages'] = (int)ceil($out['total'] / $this->limit);
        $out['first'] = $out['total'] ? $this->selections->page($out['selection'], 0, min(20, $this->limit)) : [];
        return $out;
    }
}
