<?php

namespace MxAgentTools\Tool\Tv;

use MxAgentTools\Core\EntityRef;
use MxAgentTools\Core\ProcessorInterface;
use MxAgentTools\Core\Refusal;
use MxAgentTools\Core\Values;
use MxAgentTools\Entity\Tv;
use MxAgentTools\Tool\AbstractTool;
use MxAgentTools\Tool\WriteToolInterface;

class TvUpdate extends AbstractTool implements WriteToolInterface
{
    const NAME = 'tv.update';
    const TITLE = 'Изменить TV';
    const PATH = '/agent/tvs/{id:\\d+}';
    const KIND = self::WRITE;
    const DESCRIPTION = 'Правка TV процессором element/tv/update: fields, input_properties, output_properties и привязка '
        . 'к шаблонам (templates — полный список id; отвязка от шаблона не удаляет значения у ресурсов).';

    /** @var ProcessorInterface */
    private $processor;
    /** @var Tv */
    private $tv;

    public function __construct(ProcessorInterface $processor, Tv $tv)
    {
        $this->processor = $processor;
        $this->tv = $tv;
    }

    protected function args(): array
    {
        return self::obj(['id' => self::int('id TV'), 'fields' => self::map('поле TV => значение'),
            'input_properties' => self::map('параметры ввода'), 'output_properties' => self::map('параметры вывода'),
            'templates' => self::ids('полный список id шаблонов')], ['id']);
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
        return [$key => TvChange::merge($before[$key], $args)];
    }

    public function apply(array $args): array
    {
        $id = Values::id($args, 'id');
        $current = (array)$this->tv->snapshot($id);
        $this->processor->run('element/tv/update', ['id' => $id] + $this->tv->data(TvChange::merge($current, $args), $current));
        return [];
    }
}
