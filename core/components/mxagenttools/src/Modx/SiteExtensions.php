<?php

namespace MxAgentTools\Modx;

/**
 * Расширения сайта: событие mxAgentToolsOnRegister (один вызов на процесс, результат кешируется).
 * Параметры события:
 *  - tools — \ArrayObject: плагин сайта добавляет объекты Tool\ToolInterface (свои инструменты);
 *  - entities — \ArrayObject: Entity\EntityInterface для записей этих инструментов (журнал, откат);
 *  - denied_classes — \ArrayObject: классы xPDO сайта, которые общий query не отдаёт (модель, загруженная плагином,
 *    иначе стала бы читаемой целиком);
 *  - selections, batch — Modx\Selections и Tool\Batch\BatchIds пакета для пары select → batch.
 * Пакет сайт не знает: что за инструменты, решает плагин сайта. Без подписчиков поведение прежнее.
 */
class SiteExtensions
{
    const EVENT = 'mxAgentToolsOnRegister';

    /** @var array|null tools, entities, denied */
    private $loaded;

    /** @param array $services Toolbox::services() — нужны selections и batch */
    public function load(\modX $modx, array $services): array
    {
        if ($this->loaded === null) {
            $tools = new \ArrayObject();
            $entities = new \ArrayObject();
            $denied = new \ArrayObject();
            $modx->invokeEvent(self::EVENT, ['tools' => $tools, 'entities' => $entities, 'denied_classes' => $denied,
                'selections' => $services['selections'], 'batch' => $services['batch']]);
            $this->loaded = ['tools' => $tools->getArrayCopy(), 'entities' => $entities->getArrayCopy(),
                'denied' => array_values(array_filter(array_map('strval', $denied->getArrayCopy())))];
        }
        return $this->loaded;
    }
}
