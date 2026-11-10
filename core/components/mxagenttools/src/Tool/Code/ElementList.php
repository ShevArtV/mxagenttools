<?php

namespace MxAgentTools\Tool\Code;

use MxAgentTools\Core\Values;
use MxAgentTools\Tool\AbstractTool;
use MxAgentTools\Tool\ReadToolInterface;

class ElementList extends AbstractTool implements ReadToolInterface
{
    const NAME = 'element.list';
    const TITLE = 'Элементы-код: список';
    const PATH = '/agent/elements';
    const DESCRIPTION = 'Чанки, шаблоны, сниппеты, плагины (type) без кода: id, name, category, description, static, static_file, '
        . 'disabled (у плагина). query — часть имени, category — id категории; до 200 штук. Код — element.get. Только чтение: '
        . 'элементы — код сайта, их меняют разработчики.';

    /** @var \modX */
    private $modx;

    public function __construct(\modX $modx)
    {
        $this->modx = $modx;
    }

    protected function args(): array
    {
        return self::obj([
            'type' => ['type' => 'string', 'enum' => array_keys(Elements::TYPES), 'description' => 'chunk | template | snippet | plugin',
                'example' => 'chunk'],
            'query' => self::str('часть имени', 'catalog'),
            'category' => self::int('id категории', 3),
        ], ['type']);
    }

    public function read(array $args): array
    {
        [$class, $nameField] = Elements::spec(Values::text($args, 'type', true));
        $categories = [];
        foreach ($this->modx->getIterator('modCategory', null, false) as $c) {
            $categories[(int)$c->get('id')] = (string)$c->get('category');
        }
        $q = $this->modx->newQuery($class);
        if (Values::text($args, 'query') !== '') {
            $q->where([$nameField . ':LIKE' => '%' . Values::text($args, 'query') . '%']);
        }
        if (!empty($args['category'])) {
            $q->where(['category' => (int)$args['category']]);
        }
        $q->sortby($this->modx->escape($nameField), 'ASC');
        $q->limit(200);
        $out = [];
        foreach ($this->modx->getIterator($class, $q, false) as $e) {
            $row = ['id' => (int)$e->get('id'), 'name' => (string)$e->get($nameField),
                'category' => $categories[(int)$e->get('category')] ?? '', 'description' => (string)$e->get('description'),
                'static' => (bool)$e->get('static'), 'static_file' => (string)$e->get('static_file')];
            if ($class === 'modPlugin') {
                $row['disabled'] = (bool)$e->get('disabled');
            }
            $out[] = $row;
        }
        return ['type' => $args['type'], 'elements' => $out, 'total' => $this->modx->getCount($class, $q)];
    }
}
