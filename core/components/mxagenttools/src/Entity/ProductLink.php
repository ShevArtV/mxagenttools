<?php

namespace MxAgentTools\Entity;

use MxAgentTools\Core\LinkPairs;
use MxAgentTools\Modx\ProductLinks;

/**
 * Одна строка ms2_product_links; id — «link:master:slave». Пишется напрямую, не процессорами ms2: те для
 * many_to_many склеивают группы целиком (create) и стирают все связи товара (remove), и откат такой записи не
 * был бы точным. Снимок — {link, master, slave} строками, как у прочих сущностей; null — строки нет.
 */
class ProductLink implements EntityInterface
{
    /** @var ProductLinks */
    private $links;

    public function __construct(ProductLinks $links)
    {
        $this->links = $links;
    }

    public function type(): string
    {
        return 'product_link';
    }

    public function snapshot($id): ?array
    {
        [$link, $master, $slave] = LinkPairs::parse((string)$id);
        return $this->links->exists($link, $master, $slave) ? ['link' => (string)$link, 'master' => (string)$master, 'slave' => (string)$slave] : null;
    }

    public function restore(array $snapshot): void
    {
        $this->links->add((int)$snapshot['link'], (int)$snapshot['master'], (int)$snapshot['slave']);
    }

    public function remove($id): void
    {
        [$link, $master, $slave] = LinkPairs::parse((string)$id);
        $this->links->delete($link, $master, $slave);
    }
}
