<?php

namespace MxAgentTools\Core;

/** Запись процессорами MODX — как кнопками админки (события, плагины, права, «Кем изменено», сброс кэша) */
interface ProcessorInterface
{
    /**
     * Процессор ядра ($package = '') или пакета (core/components/<package>/processors/); ошибка — RuntimeException.
     * @return array объект ответа (getObject)
     */
    public function run(string $action, array $data, string $package = ''): array;

    /** resource/create|update с производным процессором по class_key; ответ — объект ответа и имя класса процессора */
    public function resource(string $action, array $data, string $classKey): array;
}
