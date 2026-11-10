<?php

namespace MxAgentTools\Tool\Tv;

use MxAgentTools\Core\Values;
use MxAgentTools\Tool\AbstractTool;
use MxAgentTools\Tool\ReadToolInterface;

class TvList extends AbstractTool implements ReadToolInterface
{
    const NAME = 'tv.list';
    const TITLE = 'Список TV';
    const PATH = '/agent/tvs';
    const DESCRIPTION = 'Список TV: id, имя, подпись, тип, категория, id шаблонов; фильтры — шаблон и текст в имени/подписи.';

    /** @var \modX */
    private $modx;

    public function __construct(\modX $modx)
    {
        $this->modx = $modx;
    }

    protected function args(): array
    {
        return self::obj(['template' => self::int('только TV этого шаблона'), 'query' => self::str('текст в имени или подписи')]);
    }

    public function read(array $args): array
    {
        $q = $this->modx->newQuery('modTemplateVar');
        if (!empty($args['template'])) {
            $q->innerJoin('modTemplateVarTemplate', 'TVT', 'TVT.tmplvarid = modTemplateVar.id');
            $q->where(['TVT.templateid' => (int)$args['template']]);
        }
        $text = Values::text($args, 'query');
        if ($text !== '') {
            $q->where(['name:LIKE' => "%{$text}%", 'OR:caption:LIKE' => "%{$text}%"]);
        }
        $q->sortby('modTemplateVar.name', 'ASC');
        $links = [];
        foreach ($this->modx->getIterator('modTemplateVarTemplate', null, false) as $link) {
            $links[(int)$link->get('tmplvarid')][] = (int)$link->get('templateid');
        }
        $out = [];
        foreach ($this->modx->getIterator('modTemplateVar', $q, false) as $tv) {
            $id = (int)$tv->get('id');
            $out[] = ['id' => $id, 'name' => $tv->get('name'), 'caption' => $tv->get('caption'), 'type' => $tv->get('type'),
                'category' => (int)$tv->get('category'), 'templates' => $links[$id] ?? []];
        }
        return ['tvs' => $out];
    }
}
