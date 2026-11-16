<?php

namespace MxAgentTools\Tests\Fakes;

use MxAgentTools\Modx\ProductLinks;

/** Связи товаров в памяти: строки «link:master:slave»; товары 1–99 существуют; тип 1 — many_to_many, 2 — one_to_many */
class MemoryProductLinks extends ProductLinks
{
    /** @var string[] */
    public $rows = [];

    public function __construct()
    {
    }

    public function guardWrite(): void
    {
    }

    public function types(): array
    {
        return [['id' => 1, 'name' => 'Связанные', 'type' => 'many_to_many', 'description' => ''],
            ['id' => 2, 'name' => 'Аксессуары', 'type' => 'one_to_many', 'description' => '']];
    }

    public function title(int $id): string
    {
        if ($id > 99) {
            throw new \MxAgentTools\Core\Refusal("{$id} — не товар miniShop2");
        }
        return "Товар {$id}";
    }

    public function exists(int $link, int $master, int $slave): bool
    {
        return in_array("{$link}:{$master}:{$slave}", $this->rows, true);
    }

    public function slaves(int $link, int $product): int
    {
        return count(array_filter($this->rows, function ($r) use ($link, $product) {
            return strpos($r, "{$link}:{$product}:") === 0;
        }));
    }

    public function add(int $link, int $master, int $slave): void
    {
        if (!$this->exists($link, $master, $slave)) {
            $this->rows[] = "{$link}:{$master}:{$slave}";
        }
    }

    public function delete(int $link, int $master, int $slave): void
    {
        $this->rows = array_values(array_diff($this->rows, ["{$link}:{$master}:{$slave}"]));
    }
}
