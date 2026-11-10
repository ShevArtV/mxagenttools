<?php

namespace MxAgentTools\Tool\Gallery;

use MxAgentTools\Core\Refusal;

/** Позиция в списке: first | last | N (с 1) */
final class Position
{
    /** Вставить $item в список на позицию */
    public static function insert(array $list, $item, $pos): array
    {
        $pos = (string)($pos ?? 'last');
        if ($pos === 'first') {
            $i = 0;
        } elseif ($pos === 'last' || $pos === '') {
            $i = count($list);
        } elseif (ctype_digit($pos) && (int)$pos >= 1) {
            $i = min((int)$pos - 1, count($list));
        } else {
            throw new Refusal("позиция «{$pos}»: нужна first, last или номер с 1");
        }
        array_splice($list, $i, 0, [$item]);
        return array_values($list);
    }

    /** Новый порядок — перестановка текущих id, без пропусков и лишних */
    public static function permutation(array $current, array $order): array
    {
        $order = array_values(array_map('intval', $order));
        $a = $current;
        $b = $order;
        sort($a);
        sort($b);
        if ($a !== $b) {
            throw new Refusal('order: нужен полный список id фото галереи без повторов — сейчас ' . implode(',', $current));
        }
        return $order;
    }
}
