<?php

namespace MxAgentTools\Modx;

use MxAgentTools\Core\Canon;
use MxAgentTools\Core\Values;

/**
 * Данные товара miniShop2 для снимка ресурса и для данных процессора msProductUpdateProcessor.
 * product — поля msProductData (JSON-поля color/size/tags — списками), options — msProductOption вне JSON-полей
 * (ms2 сам копирует JSON-поля в опции), categories — доп. категории msCategoryMember.
 */
class ProductData
{
    /** @var \modX */
    private $modx;
    /** @var string[]|null */
    private $jsonFields;

    public function __construct(\modX $modx)
    {
        $this->modx = $modx;
    }

    /** {product, options, categories}; у ресурса нет msProductData — [] */
    public function snapshot(int $id): array
    {
        $data = $this->modx->getObject('msProductData', $id, false);
        if (!$data) {
            return [];
        }
        $product = [];
        foreach (array_keys($this->modx->getFields('msProductData')) as $field) {
            if ($field === 'id') {
                continue;
            }
            $product[$field] = in_array($field, $this->jsonFields(), true)
                ? Values::strList($data->get($field)) : Values::str($data->get($field));
        }
        return ['product' => $product, 'options' => $this->options($id), 'categories' => $this->categories($id)];
    }

    /**
     * Поля для resource/update|create товара. Опции — только если меняются, и тогда ВСЕ: msProductData::
     * saveProductOptions удаляет опции вне набора категории и все переданные — без полного набора пропали бы
     * опции, не привязанные к категории. Без «options-*» ms2 опции не перезаписывает (поэтому из toArray
     * товара их не берём). Доп. категории — словарём {id: 1|0}: простой список [] ms2 понимает как «ничего
     * не менять», и снять последнюю категорию списком нельзя.
     */
    public function payload(array $target, array $current): array
    {
        $out = [];
        foreach ((array)($target['product'] ?? []) as $k => $v) {
            $out[$k] = $v;
        }
        $options = (array)($target['options'] ?? []);
        $was = (array)($current['options'] ?? []);
        if (!Canon::equal($options, $was)) {
            foreach (array_unique(array_merge(array_keys($was), array_keys($options))) as $key) {
                $out['options-' . $key] = array_values((array)($options[$key] ?? []));
            }
        }
        $categories = array_map('intval', (array)($target['categories'] ?? []));
        $wasCategories = array_map('intval', (array)($current['categories'] ?? []));
        if ($categories != $wasCategories) {
            $map = [];
            foreach ($wasCategories as $id) {
                $map[$id] = 0;
            }
            foreach ($categories as $id) {
                $map[$id] = 1;
            }
            $out['categories'] = json_encode($map, JSON_FORCE_OBJECT);
        }
        return $out;
    }

    /** key => отсортированный список значений. Не getCount/xPDO-объекты: у msProductOption нет PK */
    private function options(int $id): array
    {
        $q = $this->modx->newQuery('msProductOption', ['product_id' => $id]);
        $q->select('`key`, `value`');
        $out = [];
        if ($q->prepare() && $q->stmt->execute()) {
            foreach ($q->stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                if (!in_array($row['key'], $this->jsonFields(), true)) {
                    $out[(string)$row['key']][] = (string)$row['value'];
                }
            }
        }
        ksort($out, SORT_STRING);
        foreach ($out as $k => $values) {
            sort($values, SORT_STRING);
            $out[$k] = $values;
        }
        return $out;
    }

    private function categories(int $id): array
    {
        $q = $this->modx->newQuery('msCategoryMember', ['product_id' => $id]);
        $q->select('category_id');
        $out = [];
        if ($q->prepare() && $q->stmt->execute()) {
            $out = array_map('intval', $q->stmt->fetchAll(\PDO::FETCH_COLUMN));
        }
        sort($out);
        return $out;
    }

    private function jsonFields(): array
    {
        if ($this->jsonFields === null) {
            $this->jsonFields = [];
            foreach ((array)$this->modx->getFieldMeta('msProductData') as $field => $meta) {
                if (strtolower((string)($meta['phptype'] ?? '')) === 'json') {
                    $this->jsonFields[] = (string)$field;
                }
            }
        }
        return $this->jsonFields;
    }
}
