<?php

namespace MxAgentTools\Entity;

/**
 * Объект, у которого запись удаляет файлы (оригиналы фото галереи): до записи Executor просит сохранить
 * копии того, что есть в before и пропадёт в after. Ответ keep() уходит в запись журнала (base64) и при откате
 * передаётся в restore() в снимке под ключом KEPT.
 */
interface FileKeeperInterface
{
    const KEPT = '_kept';

    /** @return array id => {name, data: base64} */
    public function keep(array $before, ?array $after): array;
}
