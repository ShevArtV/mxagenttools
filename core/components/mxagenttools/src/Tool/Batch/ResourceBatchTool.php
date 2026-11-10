<?php

namespace MxAgentTools\Tool\Batch;

use MxAgentTools\Core\Canon;
use MxAgentTools\Core\EntityRef;
use MxAgentTools\Core\Refusal;
use MxAgentTools\Entity\Resource;
use MxAgentTools\Tool\AbstractTool;
use MxAgentTools\Tool\BatchToolInterface;

/**
 * База пакетных правок ресурсов: цели — ресурсы порции (BatchIds), «станет» — change() над снимком каждого,
 * запись — по одному ресурсу процессором resource/update; неизменившиеся не пишутся. Упадёт посередине — журнал
 * уже содержит «было» всей порции, откат вернёт записанное.
 */
abstract class ResourceBatchTool extends AbstractTool implements BatchToolInterface
{
    const KIND = self::WRITE;

    /** @var BatchIds */
    protected $ids;
    /** @var Resource */
    protected $resource;

    public function __construct(BatchIds $ids, Resource $resource)
    {
        $this->ids = $ids;
        $this->resource = $resource;
    }

    /** Снимок ресурса после правки; отказ — Refusal с id объекта */
    abstract protected function change(array $snapshot, array $args): array;

    protected function args(): array
    {
        return self::obj(BatchIds::args() + $this->changeArgs());
    }

    abstract protected function changeArgs(): array;

    public function targets(array $args): array
    {
        return array_map(function (int $id) {
            return new EntityRef('resource', $id);
        }, $this->ids->ids($args));
    }

    public function preview(array $args, array $before): array
    {
        $after = [];
        foreach ($before as $key => $snapshot) {
            if ($snapshot === null) {
                throw new Refusal("ресурса {$key} нет — соберите выборку заново");
            }
            $after[$key] = $this->change($snapshot, $args);
        }
        return $after;
    }

    public function apply(array $args): array
    {
        foreach ($this->targets($args) as $ref) {
            $current = (array)$this->resource->snapshot($ref->id());
            $target = $this->change($current, $args);
            if (!Canon::equal($current, $target)) {
                $this->resource->write($target, $current);
            }
        }
        return [];
    }
}
