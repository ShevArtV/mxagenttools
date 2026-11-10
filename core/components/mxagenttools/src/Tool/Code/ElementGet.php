<?php

namespace MxAgentTools\Tool\Code;

use MxAgentTools\Core\Refusal;
use MxAgentTools\Core\Values;
use MxAgentTools\Modx\CodeFiles;
use MxAgentTools\Tool\AbstractTool;
use MxAgentTools\Tool\ReadToolInterface;

class ElementGet extends AbstractTool implements ReadToolInterface
{
    const NAME = 'element.get';
    const TITLE = 'Элемент-код целиком';
    const PATH = '/agent/elements/get';
    const DESCRIPTION = 'Чанк, шаблон, сниппет или плагин (type + id или name): поля, категория, code — код (у статичного — '
        . 'содержимое файла static_file, если он внутри сайта), properties; у плагина — events [{event, priority}], у шаблона '
        . '— tvs (имена привязанных TV). Чтобы понять, где выводится поле, прежде чем его править. Только чтение.';

    /** @var \modX */
    private $modx;
    /** @var CodeFiles */
    private $files;

    public function __construct(\modX $modx, CodeFiles $files)
    {
        $this->modx = $modx;
        $this->files = $files;
    }

    protected function args(): array
    {
        return self::obj([
            'type' => ['type' => 'string', 'enum' => array_keys(Elements::TYPES), 'description' => 'chunk | template | snippet | plugin',
                'example' => 'template'],
            'id' => self::int('id элемента', 3),
            'name' => self::str('имя элемента (у шаблона — templatename)', 'catalog.inner'),
        ], ['type']);
    }

    public function read(array $args): array
    {
        $type = Values::text($args, 'type', true);
        [$class, $nameField, $codeField] = Elements::spec($type);
        $where = !empty($args['id']) ? ['id' => (int)$args['id']] : [$nameField => Values::text($args, 'name', true)];
        /** @var \modElement $e */
        $e = $this->modx->getObject($class, $where);
        if (!$e) {
            throw new Refusal("{$type} " . json_encode($where, JSON_UNESCAPED_UNICODE) . ' не найден');
        }
        $category = $e->get('category') ? $this->modx->getObject('modCategory', (int)$e->get('category')) : null;
        $out = ['type' => $type, 'id' => (int)$e->get('id'), 'name' => (string)$e->get($nameField),
            'description' => (string)$e->get('description'), 'category' => $category ? (string)$category->get('category') : '',
            'static' => (bool)$e->get('static'), 'static_file' => (string)$e->get('static_file'),
            'code' => (string)$e->get($codeField), 'properties' => $e->get('properties') ?: []];
        $file = $this->files->staticFile($e);
        if ($file !== null) {
            $out['file'] = $file['file'];
            if (isset($file['content'])) {
                $out['code'] = $file['content'];
            } else {
                $out['file_error'] = $file['error'];
            }
        }
        if ($class === 'modPlugin') {
            $out['disabled'] = (bool)$e->get('disabled');
            $out['events'] = [];
            foreach ($this->modx->getIterator('modPluginEvent', ['pluginid' => $out['id']], false) as $pe) {
                $out['events'][] = ['event' => (string)$pe->get('event'), 'priority' => (int)$pe->get('priority')];
            }
        }
        if ($class === 'modTemplate') {
            $q = $this->modx->newQuery('modTemplateVar');
            $q->innerJoin('modTemplateVarTemplate', 'TVT', ['TVT.tmplvarid = modTemplateVar.id', 'TVT.templateid' => $out['id']]);
            $q->sortby('TVT.rank', 'ASC');
            $out['tvs'] = [];
            foreach ($this->modx->getIterator('modTemplateVar', $q, false) as $tv) {
                $out['tvs'][] = (string)$tv->get('name');
            }
        }
        return $out;
    }
}
