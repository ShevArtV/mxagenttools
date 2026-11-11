<?php

namespace MxAgentTools\Modx;

/**
 * Внешние коды модификаций от сайта: событие mxAgentToolsOnExternalIds (заводит резолвер пакета).
 * Параметры: modifications — [mid => rid, …], codes — пустой \ArrayObject; плагин сайта кладёт $codes[$mid] = '<код>'.
 * Пакет кодов не знает: чьи они (uuid 1С и т.п.) — дело сайта.
 */
class ExternalIds
{
    const EVENT = 'mxAgentToolsOnExternalIds';

    /** @var \modX */
    private $modx;

    public function __construct(\modX $modx)
    {
        $this->modx = $modx;
    }

    /**
     * @param array $modifications mid => rid
     * @return array mid => что положил плагин (как есть; отбор — у вызывающего)
     */
    public function codes(array $modifications): array
    {
        $codes = new \ArrayObject();
        $this->modx->invokeEvent(self::EVENT, ['modifications' => $modifications, 'codes' => $codes]);
        return $codes->getArrayCopy();
    }
}
