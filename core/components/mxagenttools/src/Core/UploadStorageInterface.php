<?php

namespace MxAgentTools\Core;

/** Где лежат принятые файлы до записи: таблица пакета (Modx\DbUploadStorage) */
interface UploadStorageInterface
{
    public function save(string $token, string $name, string $sha256, string $data, int $expires): void;

    /** {name, sha256, data, expires} или null */
    public function load(string $token): ?array;

    public function drop(string $token): void;

    /** Удалить просроченные (expires < $now) */
    public function purge(int $now): void;
}
