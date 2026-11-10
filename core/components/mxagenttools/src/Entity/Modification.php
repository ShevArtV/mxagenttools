<?php

namespace MxAgentTools\Entity;

use MxAgentTools\Core\Canon;
use MxAgentTools\Core\IdMap;
use MxAgentTools\Modx\Modifications;

/**
 * Модификация msOptionsPrice2: поля, опции, фото с рангами. Удалённая возвращается созданием заново (новый id —
 * в IdMap); ссылки на фото галереи, возвращённые откатом галереи раньше, переводятся через IdMap.
 * Значения опций товара ms2, добавленные option/create, к модификации не относятся — их возвращает снимок
 * ресурса-товара, если инструмент указал его в targets.
 */
class Modification implements EntityInterface
{
    /** @var Modifications */
    private $mods;
    /** @var IdMap */
    private $ids;

    public function __construct(Modifications $mods, IdMap $ids)
    {
        $this->mods = $mods;
        $this->ids = $ids;
    }

    public function type(): string
    {
        return 'modification';
    }

    public function snapshot($id): ?array
    {
        return $this->mods->snapshot((int)$id);
    }

    public function restore(array $snapshot): void
    {
        $old = (int)$snapshot['id'];
        $rid = (int)$snapshot['rid'];
        $pairs = array_map(function (array $pair) {
            return [(int)$this->ids->map('msProductFile', $pair[0]), (int)$pair[1]];
        }, (array)$snapshot['images']);
        $images = array_column($pairs, 0);
        $image = (int)$this->ids->map('msProductFile', (int)$snapshot['fields']['image']);
        $mid = (int)$this->ids->map('msopModification', $old);
        $current = $this->mods->snapshot($mid);
        if ($current === null) {
            $mid = $this->mods->create($rid, $snapshot['fields'], (array)$snapshot['options'], $images);
            $this->ids->set('msopModification', $old, $mid);
            $current = (array)$this->mods->snapshot($mid);
        }
        $fields = array_diff_key($snapshot['fields'], array_flip(Modifications::NOT_UPDATED));
        if (!Canon::equal(array_diff_key($current['fields'], array_flip(Modifications::NOT_UPDATED)), $fields)) {
            $this->mods->update($mid, $fields);
        }
        if (!Canon::equal($current['options'], $snapshot['options'])) {
            $this->mods->setOptions($mid, $rid, (array)$snapshot['options']);
        }
        if (array_column($this->mods->images($mid), 0) !== $images) {
            $this->mods->setImages($mid, $images);
        }
        $this->mods->exactRanks($mid, $pairs, $image);
    }

    public function remove($id): void
    {
        if ($this->mods->snapshot((int)$id) !== null) {
            $this->mods->remove((int)$id);
        }
    }
}
