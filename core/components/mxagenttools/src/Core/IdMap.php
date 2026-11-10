<?php

namespace MxAgentTools\Core;

/**
 * Замены id при откате: объект, удалённый записью, возвращается новой записью с новым id (фото галереи,
 * TV, модификация). Сущности, восстанавливаемые следом, переводят ссылки на него через map().
 * Живёт один процесс; общий экземпляр раздаёт Toolbox.
 */
final class IdMap
{
    /** @var array<string, array<int|string, int|string>> */
    private $map = [];

    /**
     * @param int|string $old
     * @param int|string $new
     */
    public function set(string $class, $old, $new): void
    {
        $this->map[$class][$old] = $new;
    }

    /**
     * @param int|string $id
     * @return int|string
     */
    public function map(string $class, $id)
    {
        return $this->map[$class][$id] ?? $id;
    }

    public function all(): array
    {
        return $this->map;
    }
}
