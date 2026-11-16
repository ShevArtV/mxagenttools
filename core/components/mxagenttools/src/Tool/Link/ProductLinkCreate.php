<?php

namespace MxAgentTools\Tool\Link;

use MxAgentTools\Core\LinkPairs;
use MxAgentTools\Core\Values;

class ProductLinkCreate extends LinkTool
{
    const NAME = 'product.link.create';
    const TITLE = 'Связать два товара';
    const PATH = '/agent/products/links/create';
    const DESCRIPTION = 'Связь одной пары товаров miniShop2: product ↔ related по типу link, both (по умолчанию да) — и обратная '
        . 'строка. Пишет ровно эти строки ms2_product_links, группы не склеивает (как делает форма товара ms2). Для «цветов '
        . 'модели» (many_to_many) НЕ использовать на каждую пару: кружки покажутся только на части карточек — берите '
        . 'product.link.set, он связывает весь набор каждый с каждым. Если у product или related уже есть связи этого типа с '
        . 'другими товарами, инструмент откажет и подскажет product.link.set; single=true — осознанно связать только эту пару. '
        . 'Повторный apply дублей не создаёт. Откат убирает записанные строки.';

    protected function args(): array
    {
        return self::obj(['product' => self::int('id товара', 1054), 'related' => self::int('id связанного товара', 1050),
            'link' => self::linkArg(), 'both' => self::flag('писать и обратную строку (related → product); по умолчанию true'),
            'single' => self::flag('связать только эту пару, даже если у товаров уже есть группа этого типа; по умолчанию false'),
        ], ['product', 'related']);
    }

    protected function present(): bool
    {
        return true;
    }

    protected function rows(array $args): array
    {
        $a = Values::id($args, 'product');
        $b = Values::id($args, 'related');
        $link = $this->link($args);
        $type = $this->kindOf($args);
        $this->links->title($a);
        $this->links->title($b);
        $rows = LinkPairs::pair($type, $a, $b, !array_key_exists('both', $args) || !empty($args['both']));
        if ($type === 'many_to_many' && empty($args['single'])) {
            foreach ([$a => $b, $b => $a] as $one => $other) {
                $mine = $this->links->slaves($link, $one);
                if ($mine && !$this->links->exists($link, $one, $other)) {
                    throw new \MxAgentTools\Core\Refusal("у товара {$one} уже есть связи типа {$link} ({$mine} шт.): одиночная связь "
                        . "не соединит {$other} с ними. Связывайте набор целиком — product.link.set (ids всех цветов модели), "
                        . 'или single=true, если нужна именно одна пара');
                }
            }
        }
        return $rows;
    }
}
