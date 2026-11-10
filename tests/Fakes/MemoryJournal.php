<?php

namespace MxAgentTools\Tests\Fakes;

use MxAgentTools\Core\JournalInterface;
use MxAgentTools\Core\Refusal;

class MemoryJournal implements JournalInterface
{
    /** @var array[] */
    public $entries = [];
    /** @var array[] каждое состояние записи по порядку (для проверки «журнал до записи») */
    public $history = [];

    public function create(array $entry): int
    {
        $id = count($this->entries) + 1;
        $this->entries[$id] = json_decode(json_encode($entry), true);
        $this->history[] = [$id, $this->entries[$id]];
        return $id;
    }

    public function write(int $id, array $entry): void
    {
        $this->entries[$id] = json_decode(json_encode($entry), true);
        $this->history[] = [$id, $this->entries[$id]];
    }

    public function read(int $id): array
    {
        if (!isset($this->entries[$id])) {
            throw new Refusal("записи журнала {$id} нет");
        }
        return ['id' => $id] + $this->entries[$id];
    }

    public function recent(int $limit, int $offset): array
    {
        return array_slice(array_reverse($this->entries, true), $offset, $limit, true);
    }
}
