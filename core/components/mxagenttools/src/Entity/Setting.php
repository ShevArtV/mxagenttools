<?php

namespace MxAgentTools\Entity;

use MxAgentTools\Core\Canon;
use MxAgentTools\Core\ProcessorInterface;
use MxAgentTools\Core\Values;

/** Системная настройка modSystemSetting; id — ключ. Пишется system/settings/* (сброс кэша настроек — в процессоре) */
class Setting implements EntityInterface
{
    const FIELDS = ['key', 'value', 'xtype', 'namespace', 'area'];

    /** @var \modX */
    private $modx;
    /** @var ProcessorInterface */
    private $processor;

    public function __construct(\modX $modx, ProcessorInterface $processor)
    {
        $this->modx = $modx;
        $this->processor = $processor;
    }

    public function type(): string
    {
        return 'setting';
    }

    public function snapshot($id): ?array
    {
        $setting = $this->modx->getObject('modSystemSetting', ['key' => (string)$id], false);
        if (!$setting) {
            return null;
        }
        $out = [];
        foreach (self::FIELDS as $k) {
            $out[$k] = Values::str($setting->get($k));
        }
        return $out;
    }

    public function restore(array $snapshot): void
    {
        $current = $this->snapshot($snapshot['key']);
        if ($current === null) {
            $this->processor->run('system/settings/create', $snapshot);
        } elseif (!Canon::equal($current, $snapshot)) {
            $this->processor->run('system/settings/update', $snapshot);
        }
    }

    public function remove($id): void
    {
        if ($this->snapshot($id) !== null) {
            $this->processor->run('system/settings/remove', ['key' => (string)$id]);
        }
    }
}
