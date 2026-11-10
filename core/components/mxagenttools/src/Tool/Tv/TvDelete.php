<?php

namespace MxAgentTools\Tool\Tv;

use MxAgentTools\Core\EntityRef;
use MxAgentTools\Core\ProcessorInterface;
use MxAgentTools\Core\Refusal;
use MxAgentTools\Core\Values;
use MxAgentTools\Tool\AbstractTool;
use MxAgentTools\Tool\WriteToolInterface;

class TvDelete extends AbstractTool implements WriteToolInterface
{
    const NAME = 'tv.delete';
    const TITLE = 'Удалить TV';
    const PATH = '/agent/tvs/{id:\\d+}/delete';
    const KIND = self::WRITE;
    const DESCRIPTION = 'Удалить TV процессором element/tv/remove вместе с привязками и значениями у ресурсов. '
        . 'Снимок до записи хранит всё — откат создаёт TV заново (с новым id) и возвращает значения.';

    /** @var ProcessorInterface */
    private $processor;

    public function __construct(ProcessorInterface $processor)
    {
        $this->processor = $processor;
    }

    protected function args(): array
    {
        return self::obj(['id' => self::int('id TV')], ['id']);
    }

    public function targets(array $args): array
    {
        return [new EntityRef('tv', Values::id($args, 'id'))];
    }

    public function preview(array $args, array $before): array
    {
        $key = 'tv:' . Values::id($args, 'id');
        if ($before[$key] === null) {
            throw new Refusal('TV ' . Values::id($args, 'id') . ' нет');
        }
        return [$key => null];
    }

    public function apply(array $args): array
    {
        $this->processor->run('element/tv/remove', ['id' => Values::id($args, 'id')]);
        return [];
    }
}
