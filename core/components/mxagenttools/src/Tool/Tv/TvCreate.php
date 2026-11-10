<?php

namespace MxAgentTools\Tool\Tv;

use MxAgentTools\Core\EntityRef;
use MxAgentTools\Core\ProcessorInterface;
use MxAgentTools\Core\Refusal;
use MxAgentTools\Entity\Tv;
use MxAgentTools\Modx\TemplateVars;
use MxAgentTools\Tool\AbstractTool;
use MxAgentTools\Tool\WriteToolInterface;

class TvCreate extends AbstractTool implements WriteToolInterface
{
    const NAME = 'tv.create';
    const TITLE = 'Создать TV';
    const PATH = '/agent/tvs';
    const KIND = self::WRITE;
    const DESCRIPTION = 'Создать TV процессором element/tv/create: fields (name обязателен, caption, type, default_text, '
        . 'elements, display, category, rank), input_properties, output_properties, templates — id шаблонов. '
        . 'Привязки-код в default_text/elements запрещены. Откат — удаление TV.';

    /** @var ProcessorInterface */
    private $processor;
    /** @var TemplateVars */
    private $tvs;
    /** @var Tv */
    private $tv;

    public function __construct(ProcessorInterface $processor, TemplateVars $tvs, Tv $tv)
    {
        $this->processor = $processor;
        $this->tvs = $tvs;
        $this->tv = $tv;
    }

    protected function args(): array
    {
        return self::obj(['fields' => self::map('поле TV => значение'), 'input_properties' => self::map('параметры ввода'),
            'output_properties' => self::map('параметры вывода'), 'templates' => self::ids('id шаблонов')], ['fields']);
    }

    public function targets(array $args): array
    {
        return [];
    }

    public function preview(array $args, array $before): array
    {
        return ['tv:new' => $this->target($args)];
    }

    public function apply(array $args): array
    {
        $id = (int)($this->processor->run('element/tv/create', $this->tv->data($this->target($args), null))['id'] ?? 0);
        if (!$id) {
            throw new \RuntimeException('element/tv/create не вернул id');
        }
        return [new EntityRef('tv', $id)];
    }

    private function target(array $args): array
    {
        $target = TvChange::merge(null, $args);
        if ($this->tvs->find($target['fields']['name'])) {
            throw new Refusal("TV «{$target['fields']['name']}» уже есть");
        }
        unset($target['values']);
        return $target;
    }
}
