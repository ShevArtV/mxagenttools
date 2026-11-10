<?php

namespace MxAgentTools\Tool\System;

use MxAgentTools\Modx\TemplateVars;
use MxAgentTools\Tool\AbstractTool;
use MxAgentTools\Tool\ReadToolInterface;

/** Шаблоны и их TV — без кода шаблона (content не отдаётся) */
class TemplateList extends AbstractTool implements ReadToolInterface
{
    const NAME = 'template.list';
    const TITLE = 'Шаблоны и их TV';
    const PATH = '/agent/templates';
    const DESCRIPTION = 'Шаблоны сайта: id, имя, описание, категория, число ресурсов и TV шаблона по порядку (id, имя, подпись, '
        . 'тип). Код шаблона не отдаётся.';

    /** @var \modX */
    private $modx;
    /** @var TemplateVars */
    private $tvs;

    public function __construct(\modX $modx, TemplateVars $tvs)
    {
        $this->modx = $modx;
        $this->tvs = $tvs;
    }

    protected function args(): array
    {
        return self::obj(['id' => self::int('только этот шаблон', 3)]);
    }

    public function read(array $args): array
    {
        $q = $this->modx->newQuery('modTemplate');
        if (!empty($args['id'])) {
            $q->where(['id' => (int)$args['id']]);
        }
        $q->select($this->modx->getSelectColumns('modTemplate', 'modTemplate', '', ['id', 'templatename', 'description', 'category']));
        $q->sortby('id', 'ASC');
        $out = [];
        foreach ($this->modx->getIterator('modTemplate', $q, false) as $t) {
            $id = (int)$t->get('id');
            $tvs = [];
            foreach ($this->tvs->ofTemplate($id) as $tv) {
                $tvs[] = ['id' => (int)$tv->get('id'), 'name' => $tv->get('name'), 'caption' => $tv->get('caption'),
                    'type' => $tv->get('type')];
            }
            $out[] = ['id' => $id, 'name' => $t->get('templatename'), 'description' => $t->get('description'),
                'category' => (int)$t->get('category'), 'resources' => $this->modx->getCount('modResource', ['template' => $id]),
                'tvs' => $tvs];
        }
        return ['templates' => $out];
    }
}
