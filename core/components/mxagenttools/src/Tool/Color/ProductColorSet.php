<?php

namespace MxAgentTools\Tool\Color;

use MxAgentTools\Core\EntityRef;
use MxAgentTools\Core\Refusal;
use MxAgentTools\Core\Values;
use MxAgentTools\Entity\ProductColor;
use MxAgentTools\Modx\ProductColors;
use MxAgentTools\Tool\AbstractTool;
use MxAgentTools\Tool\WriteToolInterface;

class ProductColorSet extends AbstractTool implements WriteToolInterface
{
    const NAME = 'product.color.set';
    const KIND = self::WRITE;
    const TITLE = 'Поставить товару образец цвета от донора';
    const PATH = '/agent/products/color/set';
    const DESCRIPTION = 'Поставить товару образец цвета (msOptionsColor) — взять готовый у донора того же цвета: товара с тем же '
        . 'значением color и непустым pattern (pattern, pattern2, color, color2; картинки галереи донора не копируются). '
        . 'Донор — donor или, без него, первый подходящий по id; строки с пустым pattern не доноры. value — значение опции color '
        . 'товара: без него берётся единственное, при нескольких — обязателен; значения, которого нет в опции color товара, '
        . 'отказ (новый цвет не заводится). Строка есть — обновляется, нет — создаётся: вторая строка не появляется. '
        . 'Строки товара со значением, которого в опции color уже нет, заменяются. Запись — процессорами msoptionscolor. '
        . 'Откат — по записи журнала.';

    /** @var ProductColors */
    private $colors;

    public function __construct(ProductColors $colors)
    {
        $this->colors = $colors;
    }

    protected function args(): array
    {
        return self::obj(['product' => self::int('id товара, которому ставим образец', 1055),
            'value' => self::str('значение опции color товара; без него — единственное значение товара', 'Белый'),
            'donor' => self::int('id товара-донора; без него — первый товар того же цвета с непустым pattern. Список — product.color.get, donors', 796)],
            ['product']);
    }

    public function targets(array $args): array
    {
        $this->colors->guardWrite();
        $p = $this->resolve($args);
        $refs = [new EntityRef('product_color', ProductColor::id($p['product'], $p['value']))];
        foreach ($p['stale'] as $value) {
            $refs[] = new EntityRef('product_color', ProductColor::id($p['product'], $value));
        }
        // Порядок отката — по целям: сначала образец, потом товар (запись опции ставит setProductOptions)
        $refs[] = new EntityRef('resource', $p['product']);
        return $refs;
    }

    public function preview(array $args, array $before): array
    {
        $p = $this->resolve($args);
        $after = $before;
        $key = 'product_color:' . ProductColor::id($p['product'], $p['value']);
        $row = $before[$key] ?? ProductColor::shape($p['product'], ['value' => $p['value']] + ProductColors::DEFAULTS);
        $after[$key] = array_replace($row, $p['fields']);
        foreach ($p['stale'] as $value) {
            $after['product_color:' . ProductColor::id($p['product'], $value)] = null;
        }
        return $after;
    }

    public function apply(array $args): array
    {
        $p = $this->resolve($args);
        foreach ($p['stale'] as $value) {
            $this->colors->delete($p['product'], $value);
        }
        $this->colors->save($p['product'], $p['value'], $p['fields']);
        return [];
    }

    /** @return array{product:int,value:string,fields:array,stale:string[]} все проверки — отказом */
    private function resolve(array $args): array
    {
        $product = Values::id($args, 'product');
        $title = $this->colors->title($product);
        $options = $this->colors->options($product);
        if (!$options) {
            throw new Refusal("у товара {$product} «{$title}» нет значения опции color — образцу не к чему привязаться; опцию ставит resource.update");
        }
        $value = trim((string)($args['value'] ?? ''));
        if ($value === '') {
            if (count($options) > 1) {
                throw new Refusal("у товара {$product} несколько значений color (" . implode(', ', $options) . ') — укажите value');
            }
            $value = $options[0];
        } elseif (!in_array($value, $options, true)) {
            throw new Refusal("значения «{$value}» нет в опции color товара {$product} (" . implode(', ', $options) . ') — новый цвет бот не заводит');
        }
        $donor = (int)($args['donor'] ?? 0);
        if ($donor === $product) {
            throw new Refusal('донор не может быть самим товаром');
        }
        if ($donor > 0) {
            $row = $this->colors->row($donor, $value);
            if ($row === null || trim($row['pattern']) === '') {
                throw new Refusal("у товара {$donor} нет образца цвета «{$value}» с непустым pattern — не донор; кандидаты — product.color.get, donors");
            }
        } else {
            $found = $this->colors->donors($value, $product, 1);
            if (!$found) {
                throw new Refusal("донора цвета «{$value}» нет: ни у одного товара нет образца с непустым pattern — картинку заводит человек");
            }
            $row = $this->colors->row($found[0]['product'], $value);
        }
        $stale = [];
        foreach ($this->colors->rows($product) as $r) {
            if (!in_array($r['value'], $options, true)) {
                $stale[] = $r['value'];
            }
        }
        return ['product' => $product, 'value' => $value, 'fields' => array_intersect_key($row, array_flip(ProductColors::COPY)),
            'stale' => $stale];
    }
}
