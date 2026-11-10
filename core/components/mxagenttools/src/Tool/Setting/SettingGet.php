<?php

namespace MxAgentTools\Tool\Setting;

use MxAgentTools\Core\Secrets;
use MxAgentTools\Core\Values;
use MxAgentTools\Tool\AbstractTool;
use MxAgentTools\Tool\ReadToolInterface;

class SettingGet extends AbstractTool implements ReadToolInterface
{
    const NAME = 'setting.get';
    const TITLE = 'Системные настройки';
    const PATH = '/agent/settings';
    const DESCRIPTION = 'Системные настройки MODX (modSystemSetting): key, value, xtype, namespace, area. key — одна настройка, '
        . 'иначе поиск по тексту в ключе (query) и/или namespace, до 200 штук. Значение настройки с ключом, похожим на '
        . 'секрет (pass, secret, token, key, private, salt, hash), — «***»: записать её можно, прочитать — нет.';

    /** @var \modX */
    private $modx;

    public function __construct(\modX $modx)
    {
        $this->modx = $modx;
    }

    protected function args(): array
    {
        return self::obj(['key' => self::str('ключ настройки', 'site_name'), 'query' => self::str('текст в ключе', 'ms2_'),
            'namespace' => self::str('namespace', 'minishop2')]);
    }

    public function read(array $args): array
    {
        $q = $this->modx->newQuery('modSystemSetting');
        $key = Values::text($args, 'key');
        if ($key !== '') {
            $q->where(['key' => $key]);
        }
        if (Values::text($args, 'query') !== '') {
            $q->where(['key:LIKE' => '%' . Values::text($args, 'query') . '%']);
        }
        if (Values::text($args, 'namespace') !== '') {
            $q->where(['namespace' => Values::text($args, 'namespace')]);
        }
        $q->sortby('key', 'ASC');
        $q->limit(200);
        $out = [];
        foreach ($this->modx->getIterator('modSystemSetting', $q, false) as $s) {
            $out[] = Secrets::row(['key' => $s->get('key'), 'value' => (string)$s->get('value'), 'xtype' => $s->get('xtype'),
                'namespace' => $s->get('namespace'), 'area' => $s->get('area')]);
        }
        return ['settings' => $out];
    }
}
