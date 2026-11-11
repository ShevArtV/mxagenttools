<?php

namespace MxAgentTools\Tests\Fakes;

use MxAgentTools\Modx\Modifications;

/** Модификации в памяти: [rid => ['title' => pagetitle, 'mods' => [mid => ['name' => …, 'options' => […]]]]] */
class FakeModifications extends Modifications
{
    /** @var array */
    private $products;

    public function __construct(array $products)
    {
        $this->products = $products;
    }

    public function products(array $ids): array
    {
        $out = [];
        foreach ($ids as $id) {
            if (isset($this->products[$id])) {
                $out[$id] = $this->products[$id]['title'];
            }
        }
        return $out;
    }

    public function ofProduct(int $rid): array
    {
        return array_keys($this->products[$rid]['mods'] ?? []);
    }

    public function names(int $rid): array
    {
        return array_map(function (array $m) {
            return $m['name'] ?? '';
        }, $this->products[$rid]['mods'] ?? []);
    }

    public function options(int $mid): array
    {
        foreach ($this->products as $p) {
            if (isset($p['mods'][$mid])) {
                return $p['mods'][$mid]['options'] ?? [];
            }
        }
        return [];
    }
}
