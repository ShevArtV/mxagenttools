<?php

namespace MxAgentTools\Core;

/**
 * Новая пара (price, old_price) по правилу product.prices — без MODX, чтобы проверяться тестами.
 * op: percent (±N %), add (±N), set (ровно N); round: 0 — без округления (до копеек), 1 / 10 / 100 — до шага;
 * direction: nearest | down | up. mode: price — меняется только price; discount — новая цена в price, прежняя
 * в old_price (скидка; old_price уже стоит — сохраняется, база для расчёта — она же); undiscount — снять скидку:
 * old_price → price, old_price = 0 (op не нужен). Цена ≤ 0 — отказ, а не тихий ноль.
 */
final class PriceRule
{
    const OPS = ['percent', 'add', 'set'];
    const MODES = ['price', 'discount', 'undiscount'];
    const DIRECTIONS = ['nearest', 'down', 'up'];
    const ROUNDS = [0, 1, 10, 100];

    /** @var string */
    private $op;
    /** @var float */
    private $value;
    /** @var int */
    private $round;
    /** @var string */
    private $direction;
    /** @var string */
    private $mode;

    public function __construct(string $op, $value, int $round = 0, string $direction = 'nearest', string $mode = 'price')
    {
        if (!in_array($mode, self::MODES, true)) {
            throw new Refusal('mode: ' . implode(' | ', self::MODES));
        }
        if ($mode !== 'undiscount') {
            if (!in_array($op, self::OPS, true)) {
                throw new Refusal('op: ' . implode(' | ', self::OPS));
            }
            if (!is_numeric($value)) {
                throw new Refusal('value: нужно число');
            }
        }
        if (!in_array($round, self::ROUNDS, true)) {
            throw new Refusal('round: 0, 1, 10 или 100');
        }
        if (!in_array($direction, self::DIRECTIONS, true)) {
            throw new Refusal('direction: ' . implode(' | ', self::DIRECTIONS));
        }
        $this->op = $op;
        $this->value = (float)$value;
        $this->round = $round;
        $this->direction = $direction;
        $this->mode = $mode;
    }

    /**
     * @param string $what для текста отказа: «товар 12», «модификация 7»
     * @return array{0: string, 1: string} [price, old_price] строками, как в снимках
     */
    public function apply(string $price, string $oldPrice, string $what): array
    {
        $p = (float)$price;
        $old = (float)$oldPrice;
        if ($this->mode === 'undiscount') {
            return $old > 0 ? self::keep([self::fmt($old), '0'], $price, $oldPrice) : [$price, $oldPrice];
        }
        $base = $this->mode === 'discount' && $old > 0 ? $old : $p;
        $new = $this->round($this->compute($base));
        if ($new <= 0) {
            throw new Refusal("{$what}: цена стала бы " . self::fmt($new) . ' — правило не применяю');
        }
        if ($this->mode === 'discount') {
            return self::keep([self::fmt($new), self::fmt($base)], $price, $oldPrice);
        }
        return self::keep([self::fmt($new), $oldPrice], $price, $oldPrice);
    }

    /** Численно то же («11500.00» и «11500») — прежние строки: иначе diff показал бы правку без правки */
    private static function keep(array $new, string $price, string $oldPrice): array
    {
        return [abs((float)$new[0] - (float)$price) < 0.005 ? $price : $new[0],
            abs((float)$new[1] - (float)$oldPrice) < 0.005 ? $oldPrice : $new[1]];
    }

    private function compute(float $base): float
    {
        if ($this->op === 'percent') {
            return $base * (1 + $this->value / 100);
        }
        return $this->op === 'add' ? $base + $this->value : $this->value;
    }

    private function round(float $v): float
    {
        if ($this->round === 0) {
            return round($v, 2);
        }
        $x = $v / $this->round;
        $x = $this->direction === 'down' ? floor($x + 1e-9) : ($this->direction === 'up' ? ceil($x - 1e-9) : round($x));
        return $x * $this->round;
    }

    private static function fmt(float $v): string
    {
        return rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
    }
}
