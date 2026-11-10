<?php

namespace MxAgentTools\Tool\Resource;

use MxAgentTools\Core\Values;
use MxAgentTools\Tool\AbstractTool;
use MxAgentTools\Tool\ReadToolInterface;

class ResourceFind extends AbstractTool implements ReadToolInterface
{
    const NAME = 'resource.find';
    const TITLE = 'Поиск ресурсов';
    const PATH = '/agent/resources';
    const DESCRIPTION = 'Поиск ресурсов по тексту в заголовке, длинном заголовке, меню, псевдониме и адресе (или по id), '
        . 'с фильтрами. Корзина по умолчанию не ищется.';

    /** @var \modX */
    private $modx;

    public function __construct(\modX $modx)
    {
        $this->modx = $modx;
    }

    protected function args(): array
    {
        return self::obj([
            'query' => self::str('текст или id'),
            'context' => self::str('контекст (web, …)'),
            'parent' => self::int('только дочерние этого ресурса'),
            'template' => self::int('только с этим шаблоном'),
            'class_key' => self::str('только этого класса (modDocument, msProduct, …)'),
            'deleted' => self::int('1 — искать и в корзине'),
            'limit' => self::int('до 200, по умолчанию 30'),
            'offset' => self::int('сдвиг'),
        ]);
    }

    public function read(array $args): array
    {
        $text = Values::text($args, 'query');
        $q = $this->modx->newQuery('modResource');
        if (empty($args['deleted'])) {
            $q->where(['deleted' => 0]);
        }
        if ($text !== '' && ctype_digit($text)) {
            $q->where(['id' => (int)$text]);
        } elseif ($text !== '') {
            $like = '%' . $text . '%';
            $q->where(['pagetitle:LIKE' => $like, 'OR:longtitle:LIKE' => $like, 'OR:menutitle:LIKE' => $like,
                'OR:alias:LIKE' => $like, 'OR:uri:LIKE' => $like]);
        }
        foreach (['context' => 'context_key', 'class_key' => 'class_key'] as $arg => $field) {
            if (Values::text($args, $arg) !== '') {
                $q->where([$field => Values::text($args, $arg)]);
            }
        }
        foreach (['parent', 'template'] as $field) {
            if (isset($args[$field]) && $args[$field] !== '') {
                $q->where([$field => (int)$args[$field]]);
            }
        }
        $q->sortby('id', 'ASC');
        [$limit, $offset] = ResourceList::limit($args);
        return ResourceList::rows($this->modx, $q, $limit, $offset);
    }
}
