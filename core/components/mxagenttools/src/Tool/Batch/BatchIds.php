<?php

namespace MxAgentTools\Tool\Batch;

use MxAgentTools\Core\Refusal;
use MxAgentTools\Core\Values;
use MxAgentTools\Modx\Selections;

/**
 * Объекты пакетной правки: явный ids (не больше лимита) или порция page выборки selection (resource.select,
 * product.select) по mxagenttools.batch_limit штук. Фильтр пакетная запись не принимает: при смене шаблона или
 * родителя он «съезжал» бы между порциями.
 */
class BatchIds
{
    /** @var Selections */
    private $selections;
    /** @var int */
    private $limit;

    public function __construct(Selections $selections, int $limit)
    {
        $this->selections = $selections;
        $this->limit = max(1, $limit);
    }

    public function limit(): int
    {
        return $this->limit;
    }

    public static function args(): array
    {
        return [
            'ids' => ['type' => 'array', 'description' => 'id объектов списком (не больше mxagenttools.batch_limit) — для мелких правок',
                'example' => [971, 972]],
            'selection' => ['type' => 'string', 'description' => 'выборка из resource.select / product.select — для любых объёмов',
                'example' => '9f86d081884c7d659a2feaa0c55ad015'],
            'page' => ['type' => 'integer', 'default' => 0, 'min' => 0,
                'description' => 'порция выборки с 0 (по batch_limit объектов); порции — pages из select', 'example' => 0],
            'batch' => ['type' => 'string', 'description' => 'id операции клиента: порции одной операции откатываются вместе '
                . '(POST /agent/journal/batch/{batch}/rollback)', 'example' => 'chat30605-plan-a1b2c3d4'],
        ];
    }

    /** @return int[] */
    public function ids(array $args): array
    {
        $selection = Values::text($args, 'selection');
        if ($selection !== '') {
            if (array_key_exists('ids', $args)) {
                throw new Refusal('ids или selection — что-то одно');
            }
            return $this->selections->page($selection, (int)($args['page'] ?? 0), $this->limit);
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', Values::items($args, 'ids')))));
        if (!$ids) {
            throw new Refusal('нужен ids (список id) или selection (+ page) из resource.select / product.select');
        }
        if (count($ids) > $this->limit) {
            throw new Refusal('ids: ' . count($ids) . " объектов больше лимита {$this->limit} — соберите выборку select и идите порциями");
        }
        return $ids;
    }
}
