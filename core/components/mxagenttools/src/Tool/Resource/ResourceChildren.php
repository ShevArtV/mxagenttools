<?php

namespace MxAgentTools\Tool\Resource;

use MxAgentTools\Tool\AbstractTool;
use MxAgentTools\Tool\ReadToolInterface;

class ResourceChildren extends AbstractTool implements ReadToolInterface
{
    const NAME = 'resource.children';
    const TITLE = 'Дочерние ресурсы';
    const PATH = '/agent/resources/{id:\\d+}/children';
    const DESCRIPTION = 'Дочерние ресурсы по порядку меню (menuindex), включая скрытые и неопубликованные; id 0 — корень контекста.';

    /** @var \modX */
    private $modx;

    public function __construct(\modX $modx)
    {
        $this->modx = $modx;
    }

    protected function args(): array
    {
        return self::obj([
            'id' => self::int('id родителя, 0 — корень'),
            'context' => self::str('контекст для корня, по умолчанию web'),
            'deleted' => self::int('1 — показать и ресурсы в корзине'),
            'limit' => self::int('до 200, по умолчанию 30'),
            'offset' => self::int('сдвиг'),
        ], ['id']);
    }

    public function read(array $args): array
    {
        $id = (int)($args['id'] ?? 0);
        $q = $this->modx->newQuery('modResource', ['parent' => $id]);
        if ($id === 0) {
            $q->where(['context_key' => (string)($args['context'] ?? 'web')]);
        }
        if (empty($args['deleted'])) {
            $q->where(['deleted' => 0]);
        }
        $q->sortby('menuindex', 'ASC');
        $q->sortby('id', 'ASC');
        [$limit, $offset] = ResourceList::limit($args);
        return ['parent' => $id] + ResourceList::rows($this->modx, $q, $limit, $offset);
    }
}
