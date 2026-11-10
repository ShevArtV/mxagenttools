<?php

namespace MxAgentTools\Tool\Setting;

use MxAgentTools\Core\EntityRef;
use MxAgentTools\Core\Refusal;
use MxAgentTools\Core\Values;
use MxAgentTools\Entity\Setting;
use MxAgentTools\Tool\AbstractTool;
use MxAgentTools\Tool\WriteToolInterface;

class SettingUpdate extends AbstractTool implements WriteToolInterface
{
    const NAME = 'setting.update';
    const KIND = self::WRITE;
    const TITLE = 'Изменить системную настройку';
    const PATH = '/agent/settings';
    const DESCRIPTION = 'Значение существующей системной настройки процессором system/settings/update (сброс кэша настроек — '
        . 'штатно). Пустое значение — clear: true.';

    /** @var Setting */
    private $setting;

    public function __construct(Setting $setting)
    {
        $this->setting = $setting;
    }

    protected function args(): array
    {
        return self::obj(['key' => self::str('ключ настройки', 'site_name'), 'value' => self::str('новое значение', 'Магазин'),
            'clear' => self::flag('true — записать пустое значение')], ['key']);
    }

    public function targets(array $args): array
    {
        return [new EntityRef('setting', Values::text($args, 'key', true))];
    }

    public function preview(array $args, array $before): array
    {
        $key = 'setting:' . Values::text($args, 'key', true);
        if ($before[$key] === null) {
            throw new Refusal('настройки ' . Values::text($args, 'key') . ' нет — инструменты настройки не создают');
        }
        return [$key => ['value' => SettingValue::from($args)] + $before[$key]];
    }

    public function apply(array $args): array
    {
        $key = Values::text($args, 'key', true);
        $current = $this->setting->snapshot($key);
        if ($current === null) {
            throw new Refusal("настройки {$key} нет");
        }
        $this->setting->restore(['value' => SettingValue::from($args)] + $current);
        return [];
    }
}
