<?php

namespace MxAgentTools\Tests\Fakes;

use MxAgentTools\Core\EntityRef;
use MxAgentTools\Tool\AbstractTool;
use MxAgentTools\Tool\BatchToolInterface;

/** Пакетная правка FakeEntity «item»: args {ids, set}; fail_at — упасть, записав объекты до этого id */
class FakeBatchTool extends AbstractTool implements BatchToolInterface
{
    const NAME = 'item.batch';
    const KIND = self::WRITE;
    const TITLE = 'Тест пакета';
    const PATH = '/agent/items/batch';

    /** @var FakeEntity */
    private $items;

    public function __construct(FakeEntity $items)
    {
        $this->items = $items;
    }

    protected function args(): array
    {
        return [];
    }

    public function targets(array $args): array
    {
        return array_map(function ($id) {
            return new EntityRef('item', $id);
        }, $args['ids']);
    }

    public function preview(array $args, array $before): array
    {
        $after = [];
        foreach ($before as $key => $b) {
            $after[$key] = array_merge($b, $args['set']);
        }
        return $after;
    }

    public function apply(array $args): array
    {
        foreach ($args['ids'] as $id) {
            if (isset($args['fail_at']) && $id === $args['fail_at']) {
                throw new \RuntimeException('процессор упал');
            }
            $this->items->store[$id] = array_merge($this->items->store[$id], $args['set']);
        }
        return [];
    }
}
