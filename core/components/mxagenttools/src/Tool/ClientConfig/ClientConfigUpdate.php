<?php

namespace MxAgentTools\Tool\ClientConfig;

use MxAgentTools\Core\EntityRef;
use MxAgentTools\Core\Refusal;
use MxAgentTools\Core\Values;
use MxAgentTools\Entity\ClientConfig;
use MxAgentTools\Tool\AbstractTool;
use MxAgentTools\Tool\Setting\SettingValue;
use MxAgentTools\Tool\WriteToolInterface;

class ClientConfigUpdate extends AbstractTool implements WriteToolInterface
{
    const NAME = 'clientconfig.update';
    const KIND = self::WRITE;
    const TITLE = 'Изменить настройку ClientConfig';
    const PATH = '/agent/clientconfig';
    const DESCRIPTION = 'Значение настройки ClientConfig процессором клиентской формы (mgr/settings/save: сброс кэша, событие '
        . 'ClientConfig_ConfigChange): глобальное или для контекста (context). Пустое значение — clear: true.';

    /** @var ClientConfig */
    private $entity;

    public function __construct(ClientConfig $entity)
    {
        $this->entity = $entity;
    }

    protected function args(): array
    {
        return self::obj(['key' => self::str('ключ настройки', 'phone'), 'value' => self::str('новое значение', '+7 900 000-00-00'),
            'clear' => self::flag('true — записать пустое значение'),
            'context' => self::str('ключ контекста — значение только для него', 'web')], ['key']);
    }

    public function targets(array $args): array
    {
        return [new EntityRef('clientconfig', Values::text($args, 'key', true))];
    }

    public function preview(array $args, array $before): array
    {
        $key = 'clientconfig:' . Values::text($args, 'key', true);
        if ($before[$key] === null) {
            throw new Refusal('настройки ClientConfig ' . Values::text($args, 'key') . ' нет');
        }
        $after = $before[$key];
        $context = Values::text($args, 'context');
        if ($context === '') {
            $after['value'] = SettingValue::from($args);
        } else {
            $after['contexts'][$context] = SettingValue::from($args);
            ksort($after['contexts'], SORT_STRING);
        }
        return [$key => $after];
    }

    public function apply(array $args): array
    {
        $this->entity->save(Values::text($args, 'key', true), SettingValue::from($args), Values::text($args, 'context'));
        return [];
    }
}
