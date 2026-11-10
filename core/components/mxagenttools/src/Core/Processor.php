<?php

namespace MxAgentTools\Core;

use RuntimeException;

/**
 * runProcessor от служебного пользователя (его ставит Modx\Bootstrap) с разбором ошибок процессора.
 * Ошибка процессора — RuntimeException с сообщением и ошибками полей: это сбой, не отказ.
 */
class Processor implements ProcessorInterface
{
    /** Классы ресурсов, у которых нет своих процессоров — работает modResource*Processor */
    const BASE_CLASSES = ['modDocument', 'modResource', 'modWebLink', 'modSymLink', 'modStaticResource'];

    /** @var \modX */
    private $modx;

    public function __construct(\modX $modx)
    {
        $this->modx = $modx;
    }

    public function run(string $action, array $data, string $package = ''): array
    {
        $options = $package === '' ? [] : ['processors_path' => MODX_CORE_PATH . "components/{$package}/processors/"];
        $response = $this->modx->runProcessor($action, $data, $options);
        $what = ($package === '' ? '' : "{$package} ") . $action . (isset($data['id']) && is_scalar($data['id']) ? " {$data['id']}" : '');
        if (!$response) {
            throw new RuntimeException("{$what}: процессор не найден");
        }
        if ($response->isError()) {
            $errors = [];
            foreach ((array)$response->getFieldErrors() as $err) {
                $errors[] = $err->field . ': ' . $err->message;
            }
            throw new RuntimeException("{$what}: " . $response->getMessage() . ($errors ? ' [' . implode('; ', $errors) . ']' : ''));
        }
        return (array)$response->getObject();
    }

    /**
     * resource/update|create сами выбирают <class_key>UpdateProcessor (msProduct, msCategory, CollectionContainer) —
     * события ms2 и Collections как в админке. Но если класса нет (модель пакета не загружена), getInstance молча
     * берёт modResourceUpdateProcessor: запись прошла бы мимо событий пакета. Поэтому — отказ до записи.
     */
    public function resource(string $action, array $data, string $classKey): array
    {
        $suffix = $action === 'resource/create' ? 'CreateProcessor' : 'UpdateProcessor';
        $class = 'modResource' . $suffix;
        if (!in_array($classKey, self::BASE_CLASSES, true)) {
            $this->modx->loadClass($classKey);
            $class = $classKey . $suffix;
            if (!class_exists($class)) {
                throw new RuntimeException("{$action}: нет {$class} — запись прошла бы мимо событий {$classKey}");
            }
        }
        return ['processor' => $class, 'object' => $this->run($action, $data)];
    }
}
