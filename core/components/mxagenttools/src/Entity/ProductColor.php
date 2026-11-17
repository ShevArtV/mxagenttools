<?php

namespace MxAgentTools\Entity;

use MxAgentTools\Modx\ProductColors;

/**
 * Образец цвета товара — одна строка modx_msop_colors (key=color); id — «товар:значение». Снимок — {value, поля}
 * строками, null — строки нет. Пишется процессорами msOptionsColor (ProductColors::save), удаляется объектом модели.
 */
class ProductColor implements EntityInterface
{
    /** @var ProductColors */
    private $colors;

    public function __construct(ProductColors $colors)
    {
        $this->colors = $colors;
    }

    public static function id(int $product, string $value): string
    {
        return $product . ':' . $value;
    }

    /** @return array{0:int,1:string} */
    public static function parse(string $id): array
    {
        $parts = explode(':', $id, 2);
        return [(int)$parts[0], (string)($parts[1] ?? '')];
    }

    public function type(): string
    {
        return 'product_color';
    }

    public function snapshot($id): ?array
    {
        [$product, $value] = self::parse((string)$id);
        $row = $this->colors->row($product, $value);
        return $row === null ? null : self::shape($product, $row);
    }

    /** Вид снимка: строка образца и товар (rid) — по rid откат знает, чей это образец */
    public static function shape(int $product, array $row): array
    {
        return ['rid' => (string)$product] + $row;
    }

    public function restore(array $snapshot): void
    {
        $fields = $snapshot;
        unset($fields['value'], $fields['rid']);
        $this->colors->save((int)$snapshot['rid'], (string)$snapshot['value'], $fields);
    }

    public function remove($id): void
    {
        [$product, $value] = self::parse((string)$id);
        $this->colors->delete($product, $value);
    }
}
