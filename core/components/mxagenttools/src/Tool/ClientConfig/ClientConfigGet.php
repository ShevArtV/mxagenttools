<?php

namespace MxAgentTools\Tool\ClientConfig;

use MxAgentTools\Core\Secrets;
use MxAgentTools\Core\Values;
use MxAgentTools\Entity\ClientConfig;
use MxAgentTools\Tool\AbstractTool;
use MxAgentTools\Tool\ReadToolInterface;

class ClientConfigGet extends AbstractTool implements ReadToolInterface
{
    const NAME = 'clientconfig.get';
    const TITLE = 'Настройки ClientConfig';
    const PATH = '/agent/clientconfig';
    const DESCRIPTION = 'Настройки ClientConfig: key, label, description, xtype, group, value (глобальное) и contexts '
        . '{контекст: значение}. key — одна настройка, иначе все по группам. '
        . 'Значения настройки с ключом, похожим на секрет (pass, secret, token, key, private, salt, hash), — «***».';

    /** @var \modX */
    private $modx;
    /** @var ClientConfig */
    private $entity;

    public function __construct(\modX $modx, ClientConfig $entity)
    {
        $this->modx = $modx;
        $this->entity = $entity;
    }

    protected function args(): array
    {
        return self::obj(['key' => self::str('ключ настройки', 'phone')]);
    }

    public function read(array $args): array
    {
        $groups = [];
        foreach ($this->modx->getIterator('cgGroup', null, false) as $g) {
            $groups[(int)$g->get('id')] = (string)$g->get('label');
        }
        $q = $this->modx->newQuery('cgSetting');
        if (Values::text($args, 'key') !== '') {
            $q->where(['key' => Values::text($args, 'key')]);
        }
        $q->sortby('`group`', 'ASC');
        $q->sortby('sortorder', 'ASC');
        $out = [];
        foreach ($this->modx->getIterator('cgSetting', $q, false) as $s) {
            $out[] = Secrets::row(['label' => $s->get('label'), 'description' => $s->get('description'),
                'group' => $groups[(int)$s->get('group')] ?? ''] + (array)$this->entity->snapshot((string)$s->get('key')));
        }
        return ['settings' => $out];
    }
}
