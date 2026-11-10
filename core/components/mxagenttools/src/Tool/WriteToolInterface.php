<?php

namespace MxAgentTools\Tool;

use MxAgentTools\Core\EntityRef;

/**
 * Инструмент записи. Цикл plan → apply → журнал → откат — в Core\Executor, инструмент его не повторяет:
 * targets — какие существующие объекты задеты (их снимки — before), preview — каким станет каждый
 * (ключ «type:id» => снимок; null — объект удаляется; «type:new» — создаётся), apply — сама запись
 * процессорами MODX, ответ — созданные объекты (их откат — remove).
 */
interface WriteToolInterface extends ToolInterface
{
    /** @return EntityRef[] */
    public function targets(array $args): array;

    /** @param array $before «type:id» => снимок|null */
    public function preview(array $args, array $before): array;

    /** @return EntityRef[] созданные объекты */
    public function apply(array $args): array;
}
