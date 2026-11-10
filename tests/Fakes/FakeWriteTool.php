<?php

namespace MxAgentTools\Tests\Fakes;

use MxAgentTools\Core\EntityRef;
use MxAgentTools\Tool\AbstractTool;
use MxAgentTools\Tool\WriteToolInterface;

/**
 * Инструмент записи над FakeEntity «item»: args {id, set: {поле: значение}} правит объект, {create: {...}} создаёт,
 * {delete: id} удаляет; fail — бросить исключение посреди записи (после частичной правки).
 */
class FakeWriteTool extends AbstractTool implements WriteToolInterface
{
    const NAME = 'item.write';
    const KIND = self::WRITE;
    const TITLE = 'Тест';
    const PATH = '/agent/items/{id:\d+}';

    /** @var FakeEntity */
    private $items;

    public function __construct(FakeEntity $items)
    {
        $this->items = $items;
    }

    protected function args(): array
    {
        return self::obj(['id' => self::int('id'), 'set' => self::map('поля')], ['id']);
    }

    public function targets(array $args): array
    {
        return isset($args['id']) ? [new EntityRef('item', $args['id'])] : [];
    }

    public function preview(array $args, array $before): array
    {
        if (isset($args['create'])) {
            return ['item:new' => $args['create']];
        }
        $key = 'item:' . $args['id'];
        if (!empty($args['delete'])) {
            return [$key => null];
        }
        return [$key => array_merge($before[$key], $args['set'] ?? [])];
    }

    public function apply(array $args): array
    {
        if (isset($args['create'])) {
            $id = count($this->items->store) + 100;
            $this->items->store[$id] = ['id' => $id] + $args['create'];
            return [new EntityRef('item', $id)];
        }
        if (!empty($args['delete'])) {
            unset($this->items->store[$args['id']]);
            return [];
        }
        $this->items->store[$args['id']] = array_merge($this->items->store[$args['id']], $args['set'] ?? []);
        if (!empty($args['fail'])) {
            throw new \RuntimeException('процессор упал');
        }
        return [];
    }
}
