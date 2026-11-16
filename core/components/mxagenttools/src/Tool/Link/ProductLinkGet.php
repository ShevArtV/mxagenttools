<?php

namespace MxAgentTools\Tool\Link;

use MxAgentTools\Core\Values;
use MxAgentTools\Modx\ProductLinks;
use MxAgentTools\Tool\AbstractTool;
use MxAgentTools\Tool\ReadToolInterface;

class ProductLinkGet extends AbstractTool implements ReadToolInterface
{
    const NAME = 'product.link.get';
    const TITLE = 'Связи товара';
    const PATH = '/agent/products/{product:\\d+}/links';
    const DESCRIPTION = 'Связи товара miniShop2 (ms2_product_links): список типов связи link_types {id, name, type} и связи товара — '
        . 'link (id и название типа), related (id, название связанного товара), direction: master (товар — master строки, '
        . 'связанный — slave) или slave. Тип many_to_many (тип 1 «связанные товары» — цвета модели) хранит обе стороны отдельными '
        . 'строками, поэтому у связанных товаров обычно по две строки (master и slave). Фильтр link — только один тип.';

    /** @var ProductLinks */
    private $links;

    public function __construct(ProductLinks $links)
    {
        $this->links = $links;
    }

    protected function args(): array
    {
        return self::obj(['product' => self::int('id товара', 1054),
            'link' => self::int('только этот тип связи (id); без него — все типы', 1)], ['product']);
    }

    public function read(array $args): array
    {
        $product = Values::id($args, 'product');
        $title = $this->links->title($product);
        $types = [];
        foreach ($this->links->types() as $type) {
            $types[$type['id']] = $type;
        }
        $only = (int)($args['link'] ?? 0);
        $titles = [];
        $out = [];
        foreach ($this->links->of($product) as [$link, $master, $slave]) {
            if ($only && $link !== $only) {
                continue;
            }
            $related = $master === $product ? $slave : $master;
            $titles[$related] = $titles[$related] ?? $this->titleOrEmpty($related);
            $out[] = ['link' => $link, 'link_name' => $types[$link]['name'] ?? '', 'link_type' => $types[$link]['type'] ?? '',
                'related' => $related, 'related_title' => $titles[$related], 'direction' => $master === $product ? 'master' : 'slave'];
        }
        return ['product' => $product, 'title' => $title, 'link_types' => array_values($types), 'links' => $out];
    }

    private function titleOrEmpty(int $id): string
    {
        try {
            return $this->links->title($id);
        } catch (\Throwable $e) {
            return ''; // связь на удалённый товар — показываем как есть
        }
    }
}
