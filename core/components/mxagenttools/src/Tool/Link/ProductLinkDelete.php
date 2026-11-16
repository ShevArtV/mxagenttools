<?php

namespace MxAgentTools\Tool\Link;

use MxAgentTools\Core\LinkPairs;
use MxAgentTools\Core\Refusal;
use MxAgentTools\Core\Values;

class ProductLinkDelete extends LinkTool
{
    const NAME = 'product.link.delete';
    const TITLE = 'Убрать связь двух товаров';
    const PATH = '/agent/products/links/delete';
    const DESCRIPTION = 'Убрать связь пары товаров: строки product ↔ related по типу link (both, по умолчанию да — обе стороны). '
        . 'Удаляет только эти строки ms2_product_links, остальные связи товаров (группа цветов) не трогает — в отличие от '
        . 'кнопки «удалить» в форме ms2 для many_to_many. Нет такой связи — отказ. Откат возвращает строки.';

    protected function args(): array
    {
        return self::obj(['product' => self::int('id товара', 1054), 'related' => self::int('id связанного товара', 1050),
            'link' => self::linkArg(), 'both' => self::flag('удалить и обратную строку; по умолчанию true')], ['product', 'related']);
    }

    protected function present(): bool
    {
        return false;
    }

    protected function rows(array $args): array
    {
        $a = Values::id($args, 'product');
        $b = Values::id($args, 'related');
        $link = $this->link($args);
        $rows = LinkPairs::pair($this->kindOf($args), $a, $b, !array_key_exists('both', $args) || !empty($args['both']));
        $found = array_filter($rows, function (array $row) use ($link) {
            return $this->links->exists($link, $row[0], $row[1]);
        });
        if (!$found) {
            throw new Refusal("связи {$a} ↔ {$b} типа {$link} нет — смотрите product.link.get");
        }
        return array_values($found);
    }
}
