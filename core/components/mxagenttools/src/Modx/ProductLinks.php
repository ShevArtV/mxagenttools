<?php

namespace MxAgentTools\Modx;

use MxAgentTools\Core\Refusal;

/** Связи товаров miniShop2: типы msLink и строки msProductLink (ms2_product_links) */
class ProductLinks
{
    /** @var \modX */
    private $modx;

    public function __construct(\modX $modx)
    {
        $this->modx = $modx;
    }

    /** Права как у процессора ms2 productlink/create|remove */
    public function guardWrite(): void
    {
        if (!$this->modx->hasPermission('msproduct_save')) {
            throw new Refusal('у пользователя агента нет права msproduct_save — связи товаров править нельзя');
        }
    }

    /** @return array[] {id, name, type, description} */
    public function types(): array
    {
        $out = [];
        foreach ($this->modx->getIterator('msLink', $this->modx->newQuery('msLink')->sortby('id')) as $link) {
            $out[] = ['id' => (int)$link->get('id'), 'name' => (string)$link->get('name'), 'type' => (string)$link->get('type'),
                'description' => (string)$link->get('description')];
        }
        return $out;
    }

    /** @return array {id, name, type, description}; нет такого типа — отказ */
    public function type(int $id): array
    {
        foreach ($this->types() as $type) {
            if ($type['id'] === $id) {
                return $type;
            }
        }
        throw new Refusal("типа связи {$id} нет — список в product.link.get (link_types)");
    }

    /** Название товара; не товар miniShop2 — отказ */
    public function title(int $id): string
    {
        $product = $this->modx->getObject('msProduct', ['id' => $id], false);
        if (!$product) {
            throw new Refusal("{$id} — не товар miniShop2");
        }
        return (string)$product->get('pagetitle');
    }

    public function exists(int $link, int $master, int $slave): bool
    {
        return (bool)$this->modx->getCount('msProductLink', ['link' => $link, 'master' => $master, 'slave' => $slave]);
    }

    /** @return int[][] строки, где товар — master или slave: [link, master, slave] */
    public function of(int $product): array
    {
        $q = $this->modx->newQuery('msProductLink');
        $q->where(['master' => $product, 'OR:slave:=' => $product]);
        $q->sortby('link')->sortby('master')->sortby('slave');
        $out = [];
        foreach ($this->modx->getIterator('msProductLink', $q) as $row) {
            $out[] = [(int)$row->get('link'), (int)$row->get('master'), (int)$row->get('slave')];
        }
        return $out;
    }

    /** Сколько товаров у $product как у master по типу связи */
    public function slaves(int $link, int $product): int
    {
        return (int)$this->modx->getCount('msProductLink', ['link' => $link, 'master' => $product]);
    }

    public function add(int $link, int $master, int $slave): void
    {
        if ($this->exists($link, $master, $slave)) {
            return;
        }
        $row = $this->modx->newObject('msProductLink');
        $row->fromArray(['link' => $link, 'master' => $master, 'slave' => $slave], '', true, true);
        if (!$row->save()) {
            throw new \RuntimeException("не записалась связь {$link}:{$master}:{$slave}");
        }
    }

    public function delete(int $link, int $master, int $slave): void
    {
        $row = $this->modx->getObject('msProductLink', ['link' => $link, 'master' => $master, 'slave' => $slave]);
        if ($row) {
            $row->remove();
        }
    }
}
