<?php

namespace MxAgentTools\Tool\Tv;

use MxAgentTools\Core\Values;
use MxAgentTools\Entity\Tv;
use MxAgentTools\Modx\TemplateVars;
use MxAgentTools\Tool\AbstractTool;
use MxAgentTools\Tool\ReadToolInterface;

class TvGet extends AbstractTool implements ReadToolInterface
{
    const NAME = 'tv.get';
    const TITLE = 'TV целиком';
    const PATH = '/agent/tvs/{id:\\d+}';
    const DESCRIPTION = 'TV по id: поля, параметры ввода/вывода, шаблоны; values=1 — и все значения у ресурсов '
        . '(иначе только их число).';

    /** @var TemplateVars */
    private $tvs;
    /** @var Tv */
    private $tv;

    public function __construct(TemplateVars $tvs, Tv $tv)
    {
        $this->tvs = $tvs;
        $this->tv = $tv;
    }

    protected function args(): array
    {
        return self::obj(['id' => self::int('id TV (имя → id — tv.list)', 12),
            'values' => self::flag('true — отдать и значения у ресурсов {id ресурса: значение}; по умолчанию только их число')],
            ['id']);
    }

    public function read(array $args): array
    {
        $snapshot = (array)$this->tv->snapshot((int)$this->tvs->get(Values::id($args, 'id'))->get('id'));
        if (empty($args['values'])) {
            $snapshot['values_count'] = count($snapshot['values']);
            unset($snapshot['values']);
        }
        return $snapshot;
    }
}
