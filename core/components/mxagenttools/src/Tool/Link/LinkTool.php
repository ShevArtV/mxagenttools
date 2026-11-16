<?php

namespace MxAgentTools\Tool\Link;

use MxAgentTools\Core\EntityRef;
use MxAgentTools\Core\LinkPairs;
use MxAgentTools\Core\Refusal;
use MxAgentTools\Modx\ProductLinks;
use MxAgentTools\Tool\AbstractTool;
use MxAgentTools\Tool\WriteToolInterface;

/** База записи связей: цели — строки ms2_product_links (сущность product_link), «станет» — строка есть или её нет */
abstract class LinkTool extends AbstractTool implements WriteToolInterface
{
    const KIND = self::WRITE;

    /** @var ProductLinks */
    protected $links;

    public function __construct(ProductLinks $links)
    {
        $this->links = $links;
    }

    /** @return int[][] строки [master, slave], которые инструмент трогает; проверки отказом */
    abstract protected function rows(array $args): array;

    /** Строка после записи: true — должна быть, false — не должно быть */
    abstract protected function present(): bool;

    public function targets(array $args): array
    {
        $this->links->guardWrite();
        $link = $this->link($args);
        return array_map(function (array $row) use ($link) {
            return new EntityRef('product_link', LinkPairs::id($link, $row[0], $row[1]));
        }, $this->rows($args));
    }

    public function preview(array $args, array $before): array
    {
        $after = [];
        foreach ($before as $key => $snapshot) {
            [$l, $master, $slave] = LinkPairs::parse(substr($key, strlen('product_link:')));
            $after[$key] = $this->present() ? ['link' => (string)$l, 'master' => (string)$master, 'slave' => (string)$slave] : null;
        }
        return $after;
    }

    public function apply(array $args): array
    {
        $link = $this->link($args);
        foreach ($this->rows($args) as $row) {
            $this->present() ? $this->links->add($link, $row[0], $row[1]) : $this->links->delete($link, $row[0], $row[1]);
        }
        return [];
    }

    protected function link(array $args): int
    {
        $id = (int)($args['link'] ?? 1);
        if ($id <= 0) {
            throw new Refusal('link: нужен id типа связи (целое больше 0)');
        }
        return $id;
    }

    /** Тип связи — msLink.type */
    protected function kindOf(array $args): string
    {
        return $this->links->type($this->link($args))['type'];
    }

    protected static function linkArg(): array
    {
        return self::int('id типа связи (msLink); по умолчанию 1 — «связанные товары», на AS так выводятся кружки других '
            . 'цветов. Типы — product.link.get, link_types', 1);
    }
}
