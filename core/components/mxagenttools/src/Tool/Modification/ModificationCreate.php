<?php

namespace MxAgentTools\Tool\Modification;

use MxAgentTools\Core\EntityRef;
use MxAgentTools\Core\Refusal;
use MxAgentTools\Core\Values;
use MxAgentTools\Modx\GalleryFiles;
use MxAgentTools\Modx\Modifications;
use MxAgentTools\Tool\AbstractTool;
use MxAgentTools\Tool\WriteToolInterface;

/**
 * Новая модификация. Задет и товар: option/create добавляет значение опции в опции товара ms2 — снимок
 * ресурса-товара в targets, и откат возвращает его опции.
 */
class ModificationCreate extends AbstractTool implements WriteToolInterface
{
    const NAME = 'modification.create';
    const KIND = self::WRITE;
    const TITLE = 'Создать модификацию';
    const PATH = '/agent/products/{product:\\d+}/modifications';
    const DESCRIPTION = 'Новая модификация msOptionsPrice2 товара процессорами пакета, как в админке: опции (option/create, '
        . 'значения попадают и в опции товара ms2) → modification/create → фото (misc/gallery/update, первое — rank 0). '
        . 'fields: price (обязательно), old_price, count, active (по умолчанию 1), name, article, weight, type (по умолчанию '
        . 'как у других модификаций товара). images — id фото из галереи товара (gallery.get). Отказ, если у товара есть '
        . 'недосохранённые опции mid=0. Откат удаляет модификацию и возвращает опции товара.';

    /** @var Modifications */
    private $mods;
    /** @var GalleryFiles */
    private $files;

    public function __construct(Modifications $mods, GalleryFiles $files)
    {
        $this->mods = $mods;
        $this->files = $files;
    }

    protected function args(): array
    {
        return self::obj([
            'product' => self::int('id товара', 25),
            'fields' => self::map('поля модификации', ['price' => 1990, 'old_price' => 0, 'count' => 5, 'active' => 1]),
            'options' => self::map('опции {ключ: значение|[значения]}', ['color' => 'синий', 'size' => 'M']),
            'images' => self::ids('id фото галереи товара по порядку', [101, 102]),
        ], ['product', 'fields']);
    }

    public function targets(array $args): array
    {
        return [new EntityRef('resource', Values::id($args, 'product'))];
    }

    public function preview(array $args, array $before): array
    {
        $rid = Values::id($args, 'product');
        $product = $before["resource:{$rid}"];
        if ($product === null || !isset($product['product'])) {
            throw new Refusal("товара {$rid} нет");
        }
        $mod = $this->target($rid, $args);
        foreach ($mod['options'] as $key => $values) {
            $group = isset($product['product'][$key]) && is_array($product['product'][$key]) ? 'product' : 'options';
            $list = array_values(array_unique(array_merge((array)($product[$group][$key] ?? []), $values)));
            if ($group === 'options') {
                sort($list, SORT_STRING);
            }
            $product[$group][$key] = $list;
        }
        ksort($product['options'], SORT_STRING);
        return ["resource:{$rid}" => $product, 'modification:new' => $mod];
    }

    public function apply(array $args): array
    {
        $rid = Values::id($args, 'product');
        $mod = $this->target($rid, $args);
        $mid = $this->mods->create($rid, $mod['fields'], $mod['options'], array_column($mod['images'], 0));
        $this->files->refresh();
        return [new EntityRef('modification', $mid)];
    }

    private function target(int $rid, array $args): array
    {
        $this->mods->assertNoPending($rid);
        $fields = Values::strMap(Values::map($args, 'fields'));
        foreach (array_keys($fields) as $k) {
            if (!in_array($k, $this->mods->fieldNames(), true) || in_array($k, Modifications::NOT_UPDATED, true)) {
                throw new Refusal("поле модификации «{$k}» не задаётся (фото — images)");
            }
        }
        if (!isset($fields['price']) || !is_numeric($fields['price'])) {
            throw new Refusal('fields.price: нужна цена числом');
        }
        if (!isset($fields['type'])) {
            $first = $this->mods->ofProduct($rid)[0] ?? 0;
            $fields['type'] = $first ? (string)$this->mods->snapshot($first)['fields']['type'] : '1';
        }
        $fields += ['active' => '1', 'old_price' => '0', 'count' => '0'];
        $gallery = $this->files->files($rid);
        $images = ModificationChange::images(Values::items($args, 'images'));
        foreach ($images as $pair) {
            if (!isset($gallery[$pair[0]]) || $gallery[$pair[0]]['type'] !== 'image') {
                throw new Refusal("фото {$pair[0]} нет в галерее товара {$rid} (или это не картинка) — сначала gallery.upload");
            }
        }
        return ['rid' => $rid, 'fields' => $fields, 'options' => ModificationChange::options(Values::map($args, 'options')),
            'images' => $images];
    }
}
