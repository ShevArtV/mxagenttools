<?php

namespace MxAgentTools\Tool\System;

use MxAgentTools\Tool\AbstractTool;
use MxAgentTools\Tool\ReadToolInterface;

class ContextList extends AbstractTool implements ReadToolInterface
{
    const NAME = 'context.list';
    const TITLE = 'Контексты';
    const PATH = '/agent/contexts';
    const DESCRIPTION = 'Контексты сайта (кроме mgr): ключ, имя, описание, порядок, site_url и site_start из настроек контекста.';

    /** @var \modX */
    private $modx;

    public function __construct(\modX $modx)
    {
        $this->modx = $modx;
    }

    protected function args(): array
    {
        return [];
    }

    public function read(array $args): array
    {
        $settings = [];
        $q = $this->modx->newQuery('modContextSetting', ['key:IN' => ['site_url', 'site_start']]);
        foreach ($this->modx->getIterator('modContextSetting', $q, false) as $s) {
            $settings[(string)$s->get('context_key')][(string)$s->get('key')] = (string)$s->get('value');
        }
        $q = $this->modx->newQuery('modContext', ['key:!=' => 'mgr']);
        $q->sortby('rank', 'ASC');
        $out = [];
        foreach ($this->modx->getIterator('modContext', $q, false) as $c) {
            $key = (string)$c->get('key');
            $out[] = ['key' => $key, 'name' => $c->get('name'), 'description' => $c->get('description'),
                'rank' => (int)$c->get('rank')] + ($settings[$key] ?? []);
        }
        return ['contexts' => $out];
    }
}
