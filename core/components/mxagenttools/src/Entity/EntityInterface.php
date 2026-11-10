<?php

namespace MxAgentTools\Entity;

/**
 * Вид объекта сайта для общего цикла записи и отката (Core\Executor).
 * snapshot — полное состояние объекта как JSON-совместимый массив (строки, списки, словари), null — объекта нет;
 * restore — привести объект к снимку (создать заново, если его нет); remove — удалить объект, созданный
 * инструментом. Новый вид объекта — новый класс, ядро не меняется.
 */
interface EntityInterface
{
    public function type(): string;

    /** @param int|string $id */
    public function snapshot($id): ?array;

    public function restore(array $snapshot): void;

    /** @param int|string $id */
    public function remove($id): void;
}
