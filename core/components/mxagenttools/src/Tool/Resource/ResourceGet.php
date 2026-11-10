<?php

namespace MxAgentTools\Tool\Resource;

use MxAgentTools\Core\Refusal;
use MxAgentTools\Core\Values;
use MxAgentTools\Entity\Resource;
use MxAgentTools\Tool\AbstractTool;
use MxAgentTools\Tool\ReadToolInterface;

class ResourceGet extends AbstractTool implements ReadToolInterface
{
    const NAME = 'resource.get';
    const TITLE = 'Ресурс целиком';
    const PATH = '/agent/resources/{id:\\d+}';
    const DESCRIPTION = 'Ресурс целиком: все поля, сырые значения всех TV шаблона (с подписями), у товара — данные, опции, '
        . 'доп. категории; кем и когда изменён, число дочерних. Это же — снимок «было» для resource.update.';

    /** @var \modX */
    private $modx;
    /** @var Resource */
    private $resource;

    public function __construct(\modX $modx, Resource $resource)
    {
        $this->modx = $modx;
        $this->resource = $resource;
    }

    protected function args(): array
    {
        return self::obj(['id' => self::int('id ресурса')], ['id']);
    }

    public function read(array $args): array
    {
        $id = Values::id($args, 'id');
        $snapshot = $this->resource->snapshot($id);
        if ($snapshot === null) {
            throw new Refusal("ресурса {$id} нет");
        }
        $res = $this->modx->getObject('modResource', $id, false);
        $editor = $res->get('editedby') ? $this->modx->getObject('modUser', (int)$res->get('editedby')) : null;
        $captions = [];
        foreach (array_keys($snapshot['tvs']) as $name) {
            $tv = $this->modx->getObject('modTemplateVar', ['name' => $name]);
            $captions[$name] = $tv ? (string)$tv->get('caption') : '';
        }
        return $snapshot + [
            'tv_captions' => $captions,
            'children' => $this->modx->getCount('modResource', ['parent' => $id]),
            'edited' => ['by' => $editor ? $editor->get('username') : null, 'on' => $res->get('editedon')],
            'created' => $res->get('createdon'), 'publishedon' => $res->get('publishedon'),
        ];
    }
}
