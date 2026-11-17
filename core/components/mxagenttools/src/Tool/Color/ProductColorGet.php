<?php

namespace MxAgentTools\Tool\Color;

use MxAgentTools\Core\Values;
use MxAgentTools\Modx\ProductColors;
use MxAgentTools\Tool\AbstractTool;
use MxAgentTools\Tool\ReadToolInterface;

class ProductColorGet extends AbstractTool implements ReadToolInterface
{
    const NAME = 'product.color.get';
    const TITLE = 'Образец цвета товара';
    const PATH = '/agent/products/{product:\\d+}/color';
    const DESCRIPTION = 'Образец цвета товара (msOptionsColor, вкладка «Цвета», таблица modx_msop_colors, ключ color): для каждого '
        . 'значения опции color товара — есть ли строка образца (exists), pattern (путь к картинке и/или hex), color, color2, '
        . 'pattern2; has_pattern — годится ли строка как образец для других. rows_without_option — строки, значения которых '
        . 'в опции color уже нет. donors — по значению без образца: товары того же цвета с непустым pattern (кандидаты в доноры '
        . 'для product.color.set). Кружок отмечается, только если значение строки совпадает со значением опции color товара.';
    const DONORS = 5;

    /** @var ProductColors */
    private $colors;

    public function __construct(ProductColors $colors)
    {
        $this->colors = $colors;
    }

    protected function args(): array
    {
        return self::obj(['product' => self::int('id товара', 1054)], ['product']);
    }

    public function read(array $args): array
    {
        $product = Values::id($args, 'product');
        $title = $this->colors->title($product);
        $options = $this->colors->options($product);
        $rows = [];
        foreach ($this->colors->rows($product) as $row) {
            $rows[$row['value']] = $row;
        }
        $colors = [];
        $donors = [];
        foreach ($options as $value) {
            $row = $rows[$value] ?? null;
            $has = $row !== null && trim($row['pattern']) !== '';
            $colors[] = ['value' => $value, 'exists' => $row !== null, 'has_pattern' => $has] + ($row ?? []);
            if (!$has) {
                $donors[$value] = $this->colors->donors($value, $product, self::DONORS);
            }
        }
        return ['product' => $product, 'title' => $title, 'options' => $options, 'colors' => $colors,
            'rows_without_option' => array_values(array_diff_key($rows, array_flip($options))), 'donors' => $donors];
    }
}
