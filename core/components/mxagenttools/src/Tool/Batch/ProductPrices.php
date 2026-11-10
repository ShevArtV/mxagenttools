<?php

namespace MxAgentTools\Tool\Batch;

use MxAgentTools\Core\Canon;
use MxAgentTools\Core\EntityRef;
use MxAgentTools\Core\PriceRule;
use MxAgentTools\Core\Refusal;
use MxAgentTools\Entity\Resource;
use MxAgentTools\Modx\Modifications;
use MxAgentTools\Tool\AbstractTool;
use MxAgentTools\Tool\BatchToolInterface;

/**
 * Цены товаров порции и (с msOptionsPrice2) их модификаций по одному правилу (Core\PriceRule). Цели — товары и
 * модификации; запись — товар процессором resource/update, модификация — mgr/modification/update пакета.
 */
class ProductPrices extends AbstractTool implements BatchToolInterface
{
    const NAME = 'product.prices';
    const KIND = self::WRITE;
    const TITLE = 'Цены товаров массово';
    const PATH = '/agent/products/prices';
    const DESCRIPTION = 'Изменить цены многих товаров (и их модификаций msOptionsPrice2, если modifications не 0) одним планом. '
        . 'op: percent (value — ±проценты), add (±сумма), set (ровно value). round: 0 — до копеек, 1 / 10 / 100 — до шага; '
        . 'direction: nearest | down | up. price_mode: price — меняется только цена; discount — скидка: новая цена → price, прежняя '
        . '→ old_price (если old_price уже стоит, база — она); undiscount — снять скидку: old_price → price, old_price = 0 '
        . '(op и value не нужны). Объекты — ids или selection + page из product.select (раздел вместе с доп. категориями). '
        . 'Цена ≤ 0 — отказ всего плана. План: total, changed, summary, diff первых 20 объектов, more.';

    /** @var BatchIds */
    private $ids;
    /** @var Resource */
    private $resource;
    /** @var Modifications|null */
    private $mods;

    public function __construct(BatchIds $ids, Resource $resource, ?Modifications $mods)
    {
        $this->ids = $ids;
        $this->resource = $resource;
        $this->mods = $mods;
    }

    protected function args(): array
    {
        return self::obj(BatchIds::args() + [
            'op' => ['type' => 'string', 'enum' => PriceRule::OPS, 'description' => 'percent | add | set', 'example' => 'percent'],
            'value' => ['type' => 'number', 'description' => 'проценты, сумма или цена — по op', 'example' => -10],
            'round' => ['type' => 'integer', 'enum' => PriceRule::ROUNDS, 'default' => 0, 'description' => '0 | 1 | 10 | 100', 'example' => 10],
            'direction' => ['type' => 'string', 'enum' => PriceRule::DIRECTIONS, 'default' => 'nearest',
                'description' => 'nearest | down | up', 'example' => 'down'],
            'price_mode' => ['type' => 'string', 'enum' => PriceRule::MODES, 'default' => 'price',
                'description' => 'price | discount | undiscount (mode — это plan/apply записи)', 'example' => 'discount'],
            'modifications' => ['type' => 'boolean', 'default' => true, 'description' => 'менять и цены модификаций (по умолчанию да)'],
        ]);
    }

    public function targets(array $args): array
    {
        $refs = [];
        foreach ($this->ids->ids($args) as $id) {
            $refs[] = new EntityRef('resource', $id);
            if ($this->withMods($args)) {
                foreach ($this->mods->ofProduct($id) as $mid) {
                    $refs[] = new EntityRef('modification', $mid);
                }
            }
        }
        return $refs;
    }

    public function preview(array $args, array $before): array
    {
        $rule = self::rule($args);
        $after = [];
        foreach ($before as $key => $snapshot) {
            $after[$key] = $snapshot === null ? null : self::change($rule, (string)$key, $snapshot);
        }
        return $after;
    }

    public function apply(array $args): array
    {
        $rule = self::rule($args);
        foreach ($this->targets($args) as $ref) {
            if ($ref->type() === 'resource') {
                $current = (array)$this->resource->snapshot($ref->id());
                $target = self::change($rule, $ref->key(), $current);
                if (!Canon::equal($current, $target)) {
                    $this->resource->write($target, $current);
                }
                continue;
            }
            $current = (array)$this->mods->snapshot((int)$ref->id());
            $target = self::change($rule, $ref->key(), $current);
            if (!Canon::equal($current['fields'], $target['fields'])) {
                $this->mods->update((int)$ref->id(), array_diff_key($target['fields'], array_flip(Modifications::NOT_UPDATED)));
            }
        }
        return [];
    }

    private static function change(PriceRule $rule, string $key, array $snapshot): array
    {
        if (strpos($key, 'modification:') === 0) {
            [$snapshot['fields']['price'], $snapshot['fields']['old_price']] = $rule->apply((string)$snapshot['fields']['price'],
                (string)$snapshot['fields']['old_price'], 'модификация ' . $snapshot['id']);
            return $snapshot;
        }
        if (!isset($snapshot['product'])) {
            throw new Refusal("ресурс {$snapshot['id']} — не товар miniShop2");
        }
        [$snapshot['product']['price'], $snapshot['product']['old_price']] = $rule->apply((string)$snapshot['product']['price'],
            (string)$snapshot['product']['old_price'], 'товар ' . $snapshot['id']);
        return $snapshot;
    }

    private static function rule(array $args): PriceRule
    {
        return new PriceRule((string)($args['op'] ?? ''), $args['value'] ?? null, (int)($args['round'] ?? 0),
            (string)($args['direction'] ?? 'nearest'), (string)($args['price_mode'] ?? 'price'));
    }

    private function withMods(array $args): bool
    {
        return $this->mods !== null && (!array_key_exists('modifications', $args) || !empty($args['modifications']));
    }
}
