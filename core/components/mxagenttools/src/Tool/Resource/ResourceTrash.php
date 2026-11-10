<?php

namespace MxAgentTools\Tool\Resource;

use MxAgentTools\Core\EntityRef;
use MxAgentTools\Core\ProcessorInterface;
use MxAgentTools\Core\Refusal;
use MxAgentTools\Core\Values;
use MxAgentTools\Tool\AbstractTool;
use MxAgentTools\Tool\WriteToolInterface;

/** Корзина MODX: resource.delete и resource.undelete отличаются процессором и направлением флага deleted */
abstract class ResourceTrash extends AbstractTool implements WriteToolInterface
{
    const KIND = self::WRITE;
    const PROCESSOR = '';
    const DELETED = '1';

    /** @var ProcessorInterface */
    private $processor;

    public function __construct(ProcessorInterface $processor)
    {
        $this->processor = $processor;
    }

    protected function args(): array
    {
        return self::obj(['id' => self::int('id ресурса')], ['id']);
    }

    public function targets(array $args): array
    {
        return [new EntityRef('resource', Values::id($args, 'id'))];
    }

    public function preview(array $args, array $before): array
    {
        $id = Values::id($args, 'id');
        $after = $before["resource:{$id}"];
        if ($after === null) {
            throw new Refusal("ресурса {$id} нет");
        }
        if ($after['fields']['deleted'] === static::DELETED) {
            throw new Refusal(static::DELETED === '1' ? "ресурс {$id} уже в корзине" : "ресурс {$id} не в корзине");
        }
        $after['fields']['deleted'] = static::DELETED;
        return ["resource:{$id}" => $after];
    }

    public function apply(array $args): array
    {
        $this->processor->run(static::PROCESSOR, ['id' => Values::id($args, 'id')]);
        return [];
    }
}
