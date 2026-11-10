<?php

namespace MxAgentTools\Core;

/**
 * Журнал записей: одна запись на apply или rollback. Запись создаётся ДО записи на сайт и дописывается после —
 * упадёт процессор посередине, откат всё равно знает «было». Хранение — таблица пакета (Modx\DbJournal).
 */
interface JournalInterface
{
    /** Новая запись; ответ — её id */
    public function create(array $entry): int;

    public function write(int $id, array $entry): void;

    /** Запись целиком; нет — Refusal */
    public function read(int $id): array;

    /** Последние записи (новые первыми), без снимков */
    public function recent(int $limit, int $offset): array;
}
