<?php

namespace MxAgentTools\Tool\Resource;

use MxAgentTools\Core\BindingGuard;
use MxAgentTools\Core\Refusal;
use MxAgentTools\Core\Values;

/**
 * Правка снимка ресурса по args resource.update — без MODX: {fields, tvs, product, options, categories}.
 * Проверяется только форма: поле есть у ресурса, TV привязан к шаблону, в TV нет привязок-кода.
 * Правила сайта (что и когда менять) — в инструкции агента, не здесь.
 */
final class ResourceChange
{
    /** Через другие инструменты: корзина — resource.delete/undelete; id не меняется */
    const DENIED_FIELDS = ['id' => 'id ресурса не меняется', 'deleted' => 'корзина — resource.delete / resource.undelete'];
    const GROUPS = ['fields', 'tvs', 'product', 'options', 'categories'];

    public static function merge(array $before, array $args): array
    {
        if (!array_intersect(self::GROUPS, array_keys($args))) {
            throw new Refusal('нечего менять: нужны fields, tvs, product, options или categories');
        }
        $after = $before;
        foreach (Values::map($args, 'fields') as $k => $v) {
            if (isset(self::DENIED_FIELDS[$k])) {
                throw new Refusal("поле {$k}: " . self::DENIED_FIELDS[$k]);
            }
            if (!array_key_exists($k, $before['fields'])) {
                throw new Refusal("у ресурса нет поля «{$k}»");
            }
            $after['fields'][$k] = self::scalar("fields.{$k}", $v);
        }
        foreach (Values::map($args, 'tvs') as $k => $v) {
            if (!array_key_exists($k, $before['tvs'])) {
                throw new Refusal("TV «{$k}» не привязан к шаблону {$before['fields']['template']} ресурса — привяжите его tv.update");
            }
            $value = is_array($v) ? implode('||', Values::strList($v)) : self::scalar("tvs.{$k}", $v);
            BindingGuard::check("tvs.{$k}", $value);
            $after['tvs'][$k] = $value;
        }
        $product = Values::map($args, 'product');
        $options = Values::map($args, 'options');
        $categories = Values::items($args, 'categories');
        if (($product || $options || array_key_exists('categories', $args)) && !isset($before['product'])) {
            throw new Refusal('product, options и categories — только у товара miniShop2');
        }
        foreach ($product as $k => $v) {
            if (!array_key_exists($k, $before['product'])) {
                throw new Refusal("у товара нет поля «{$k}»");
            }
            $after['product'][$k] = is_array($before['product'][$k]) ? Values::strList($v) : self::scalar("product.{$k}", $v);
        }
        foreach ($options as $k => $v) {
            $values = array_values(array_unique(Values::strList($v)));
            sort($values, SORT_STRING);
            if ($values) {
                $after['options'][$k] = $values;
            } else {
                unset($after['options'][$k]);
            }
        }
        if (isset($after['options'])) {
            ksort($after['options'], SORT_STRING);
        }
        if (array_key_exists('categories', $args)) {
            $ids = array_values(array_unique(array_map('intval', $categories)));
            sort($ids);
            $after['categories'] = $ids;
        }
        return $after;
    }

    private static function scalar(string $what, $v): string
    {
        if ($v !== null && !is_scalar($v)) {
            throw new Refusal("{$what}: нужна строка или число");
        }
        return Values::str($v);
    }
}
