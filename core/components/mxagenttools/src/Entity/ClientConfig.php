<?php

namespace MxAgentTools\Entity;

use MxAgentTools\Core\ProcessorInterface;
use MxAgentTools\Core\Values;

/**
 * Настройка ClientConfig (cgSetting); id — ключ. Значение — глобальное и по контекстам (cgContextValue).
 * Пишется процессором клиентской формы mgr/settings/save (сброс кэша, событие ClientConfig_ConfigChange).
 */
class ClientConfig implements EntityInterface
{
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
        return 'clientconfig';
    }

    public function snapshot($id): ?array
    {
        $setting = $this->modx->getObject('cgSetting', ['key' => (string)$id], false);
        if (!$setting) {
            return null;
        }
        $contexts = [];
        foreach ($this->modx->getIterator('cgContextValue', ['setting' => (int)$setting->get('id')], false) as $row) {
            $contexts[(string)$row->get('context')] = Values::str($row->get('value'));
        }
        ksort($contexts, SORT_STRING);
        return ['key' => (string)$id, 'xtype' => (string)$setting->get('xtype'), 'value' => Values::str($setting->get('value')),
            'contexts' => $contexts];
    }

    public function restore(array $snapshot): void
    {
        $key = (string)$snapshot['key'];
        $current = $this->snapshot($key);
        if ($current === null) {
            throw new \RuntimeException("настройки ClientConfig {$key} нет — её удалили в админке, вернуть значение некуда");
        }
        if ($current['value'] !== $snapshot['value']) {
            $this->save($key, $snapshot['value'], '');
        }
        foreach ((array)$snapshot['contexts'] as $context => $value) {
            if (($current['contexts'][$context] ?? null) !== $value) {
                $this->save($key, $value, (string)$context);
            }
        }
        // значения контекста, которых до записи не было: save умеет только писать — удаляются объектом cgContextValue
        foreach (array_diff_key((array)$current['contexts'], (array)$snapshot['contexts']) as $context => $value) {
            $setting = $this->modx->getObject('cgSetting', ['key' => $key]);
            $row = $this->modx->getObject('cgContextValue', ['setting' => (int)$setting->get('id'), 'context' => (string)$context]);
            if ($row) {
                $row->remove();
            }
        }
    }

    public function remove($id): void
    {
        throw new \LogicException('настройки ClientConfig инструменты не создают — удалять нечего');
    }

    /** Запись значения процессором; context '' — глобальное */
    public function save(string $key, string $value, string $context): void
    {
        $values = [$key => $value];
        if ($context !== '') {
            $values['context'] = $context;
        }
        // cgSettingSaveProcessor читает $_POST['values'], а не свойства процессора
        $post = $_POST['values'] ?? null;
        $_POST['values'] = json_encode($values, JSON_UNESCAPED_UNICODE);
        try {
            $this->processor->run('mgr/settings/save', ['values' => $_POST['values']], 'clientconfig');
        } finally {
            if ($post === null) {
                unset($_POST['values']);
            } else {
                $_POST['values'] = $post;
            }
        }
    }
}
