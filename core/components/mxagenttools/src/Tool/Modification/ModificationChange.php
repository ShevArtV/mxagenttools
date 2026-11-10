<?php

namespace MxAgentTools\Tool\Modification;

use MxAgentTools\Core\Refusal;
use MxAgentTools\Core\Values;
use MxAgentTools\Modx\Modifications;

/** Снимок модификации по args — без MODX: fields (price, old_price, count, active, name, article…), options, images */
final class ModificationChange
{
    public static function merge(array $before, array $args): array
    {
        $after = $before;
        foreach (Values::map($args, 'fields') as $k => $v) {
            if (in_array($k, Modifications::NOT_UPDATED, true)) {
                throw new Refusal("поле {$k}: " . ($k === 'image' ? 'фото — modification.images' : 'не меняется'));
            }
            if (!array_key_exists($k, $before['fields'])) {
                throw new Refusal("у модификации нет поля «{$k}»");
            }
            if ($v !== null && !is_scalar($v)) {
                throw new Refusal("fields.{$k}: нужна строка или число");
            }
            $after['fields'][$k] = Values::str($v);
        }
        if (isset($args['options'])) {
            $after['options'] = self::options(Values::map($args, 'options'));
        }
        if (isset($args['images'])) {
            $after['images'] = self::images(Values::items($args, 'images'));
            $after['fields']['image'] = (string)($after['images'][0][0] ?? 0);
        }
        return $after;
    }

    /** {ключ: значение|[значения]} → {ключ: [отсортированные значения]}, пустые ключи убираются */
    public static function options(array $options): array
    {
        $out = [];
        foreach ($options as $k => $v) {
            $values = array_values(array_unique(Values::strList($v)));
            sort($values, SORT_STRING);
            if ($values) {
                $out[(string)$k] = $values;
            }
        }
        ksort($out, SORT_STRING);
        return $out;
    }

    /** [id фото…] → [[id, rank 0..n-1]] — так их пересоберёт setImages */
    public static function images(array $ids): array
    {
        $out = [];
        foreach (array_values(array_unique(array_map('intval', $ids))) as $i => $id) {
            if ($id <= 0) {
                throw new Refusal('images: id фото галереи больше 0');
            }
            $out[] = [$id, $i];
        }
        return $out;
    }
}
