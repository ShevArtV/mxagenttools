<?php

namespace MxAgentTools\Core;

/**
 * Строки ms2_product_links для связи товаров — чистая логика, без базы. Тип связи msLink.type: many_to_many и
 * one_to_one хранят обе стороны, one_to_many — master → slave, many_to_one — строка пишется наоборот (slave → master).
 */
final class LinkPairs
{
    const TYPES = ['many_to_many', 'one_to_many', 'many_to_one', 'one_to_one'];

    /**
     * Строки для связи «товар $a — связанный $b»: [[master, slave], …]; $both — добавить обратную строку.
     * @return int[][]
     */
    public static function pair(string $type, int $a, int $b, bool $both): array
    {
        if (!in_array($type, self::TYPES, true)) {
            throw new Refusal("тип связи «{$type}» не поддерживается");
        }
        if ($a === $b) {
            throw new Refusal('товар нельзя связать с самим собой');
        }
        $rows = $type === 'many_to_one' ? [[$b, $a]] : [[$a, $b]];
        if ($both) {
            $rows[] = array_reverse($rows[0]);
        }
        return $rows;
    }

    /**
     * Каждый с каждым: все упорядоченные пары. Только для типов, где связь симметрична.
     * @param int[] $ids
     * @return int[][]
     */
    public static function group(string $type, array $ids): array
    {
        if (!in_array($type, ['many_to_many', 'one_to_one'], true)) {
            throw new Refusal("«каждый с каждым» — только для связей many_to_many (группа) и one_to_one, а тип связи — {$type}");
        }
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (count($ids) < 2) {
            throw new Refusal('для связи нужно не меньше двух товаров');
        }
        $rows = [];
        foreach ($ids as $a) {
            foreach ($ids as $b) {
                if ($a !== $b) {
                    $rows[] = [$a, $b];
                }
            }
        }
        return $rows;
    }

    /** Id строки как объекта сущности product_link */
    public static function id(int $link, int $master, int $slave): string
    {
        return "{$link}:{$master}:{$slave}";
    }

    /** @return int[] [link, master, slave] */
    public static function parse(string $id): array
    {
        if (!preg_match('/^(\d+):(\d+):(\d+)$/', $id, $m)) {
            throw new \InvalidArgumentException("недопустимый id связи: {$id}");
        }
        return [(int)$m[1], (int)$m[2], (int)$m[3]];
    }
}
