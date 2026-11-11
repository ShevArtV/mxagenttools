<?php

namespace MxAgentTools\Tool\Modification;

use MxAgentTools\Core\Refusal;
use MxAgentTools\Modx\ExternalIds;
use MxAgentTools\Modx\Modifications;
use MxAgentTools\Tool\AbstractTool;
use MxAgentTools\Tool\ReadToolInterface;

class ModificationExport extends AbstractTool implements ReadToolInterface
{
    const NAME = 'modification.export';
    const TITLE = 'Модификации товаров с внешним кодом';
    const PATH = '/agent/modifications/export';
    const LIMIT = 200;
    const DESCRIPTION = 'Выгрузка модификаций msOptionsPrice2 нескольких товаров (до 200 за вызов) строками {product, '
        . 'modification, name, external_id, source}: товары — в переданном порядке, модификации — по rank. name — имя '
        . 'модификации, а если оно пустое — «<название товара> — <значения опций через запятую>». external_id — uuid 1С, '
        . 'если сайт его знает (source=1c), иначе временный код mod-<товар>-<модификация> (source=temp): под ним '
        . 'модификация уходит в RetailCRM. Несуществующие id и не-товары — в missing.';

    /** @var Modifications */
    private $mods;
    /** @var ExternalIds */
    private $external;

    public function __construct(Modifications $mods, ExternalIds $external)
    {
        $this->mods = $mods;
        $this->external = $external;
    }

    protected function args(): array
    {
        return self::obj(['products' => self::ids('id товаров списком или строкой через запятую, не больше ' . self::LIMIT,
            [774, 286])], ['products']);
    }

    public function read(array $args): array
    {
        $ids = self::productIds($args['products'] ?? null);
        $titles = $this->mods->products($ids);
        $rows = [];
        $missing = [];
        $map = [];
        foreach ($ids as $rid) {
            if (!isset($titles[$rid])) {
                $missing[] = $rid;
                continue;
            }
            $names = $this->mods->names($rid);
            foreach ($this->mods->ofProduct($rid) as $mid) {
                $name = (string)($names[$mid] ?? '');
                $rows[] = ['product' => $rid, 'modification' => $mid,
                    'name' => trim($name) !== '' ? $name : self::title($titles[$rid], $this->mods->options($mid)),
                    'external_id' => "mod-{$rid}-{$mid}", 'source' => 'temp'];
                $map[$mid] = $rid;
            }
        }
        $codes = $map ? $this->external->codes($map) : [];
        foreach ($rows as $i => $row) {
            $code = $codes[$row['modification']] ?? null;
            if (is_string($code) && trim($code) !== '') {
                $rows[$i]['external_id'] = trim($code);
                $rows[$i]['source'] = '1c';
            }
        }
        return ['rows' => $rows, 'missing' => $missing];
    }

    /** «pagetitle — значения опций через ", "» (ключи по алфавиту); без опций — pagetitle */
    public static function title(string $pagetitle, array $options): string
    {
        ksort($options, SORT_STRING);
        $values = [];
        foreach ($options as $list) {
            foreach ((array)$list as $v) {
                $values[] = (string)$v;
            }
        }
        return $values ? $pagetitle . ' — ' . implode(', ', $values) : $pagetitle;
    }

    /**
     * id товаров из списка или строки «774,286», без повторов, в переданном порядке
     * @return int[]
     */
    public static function productIds($value): array
    {
        if (is_int($value) || is_string($value)) {
            $value = explode(',', (string)$value);
        }
        if (!is_array($value)) {
            throw new Refusal('products: нужен список id товаров');
        }
        $ids = [];
        foreach ($value as $v) {
            if (!is_int($v) && !is_string($v)) {
                throw new Refusal('products: нужен список id товаров');
            }
            $v = trim((string)$v);
            if ($v === '') {
                continue;
            }
            if (!ctype_digit($v) || (int)$v <= 0) {
                throw new Refusal("products: «{$v}» — не id");
            }
            $ids[(int)$v] = (int)$v;
        }
        if (!$ids) {
            throw new Refusal('products: обязателен — id товаров списком или строкой «774,286»');
        }
        if (count($ids) > self::LIMIT) {
            throw new Refusal('products: ' . count($ids) . ' товаров больше лимита ' . self::LIMIT . ' — разбейте на вызовы');
        }
        return array_values($ids);
    }
}
