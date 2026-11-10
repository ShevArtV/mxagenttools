<?php

namespace MxAgentTools\Tool\Tv;

use MxAgentTools\Core\BindingGuard;
use MxAgentTools\Core\Refusal;
use MxAgentTools\Core\Values;
use MxAgentTools\Entity\Tv;

/**
 * Снимок TV по args tv.create|update — без MODX: fields (name, caption, type, default_text, elements…),
 * input_properties, output_properties, templates (полный список id шаблонов). В default_text, elements и
 * параметрах — без привязок-кода (BindingGuard): возможные значения TV тоже разбирают привязки (@SELECT, @EVAL).
 */
final class TvChange
{
    public static function merge(?array $before, array $args): array
    {
        $after = $before ?? ['fields' => array_fill_keys(Tv::FIELDS, ''), 'input_properties' => [], 'output_properties' => [],
            'templates' => [], 'values' => []];
        if ($before === null) {
            $after['fields']['category'] = $after['fields']['rank'] = $after['fields']['locked'] = '0';
            $after['fields']['type'] = 'text';
            $after['fields']['display'] = 'default';
        }
        $fields = Values::map($args, 'fields');
        if (!$fields && !isset($args['templates']) && !isset($args['input_properties']) && !isset($args['output_properties'])) {
            throw new Refusal('нечего менять: нужны fields, input_properties, output_properties или templates');
        }
        foreach ($fields as $k => $v) {
            if (!in_array($k, Tv::FIELDS, true)) {
                throw new Refusal("поле TV «{$k}» не меняется (можно: " . implode(', ', Tv::FIELDS) . ')');
            }
            if ($v !== null && !is_scalar($v)) {
                throw new Refusal("fields.{$k}: нужна строка");
            }
            $after['fields'][$k] = Values::str($v);
        }
        if (!preg_match('/^[A-Za-z0-9_.\-]{1,50}$/', $after['fields']['name'])) {
            throw new Refusal('fields.name: латиница, цифры, «_», «-», «.», до 50 символов');
        }
        foreach (['input_properties', 'output_properties'] as $group) {
            foreach (Values::map($args, $group) as $k => $v) {
                $after[$group][$k] = Values::str($v);
            }
            ksort($after[$group], SORT_STRING);
        }
        BindingGuard::check('fields.default_text', $after['fields']['default_text']);
        BindingGuard::check('fields.elements', $after['fields']['elements']);
        BindingGuard::check('input_properties', $after['input_properties']);
        BindingGuard::check('output_properties', $after['output_properties']);
        if (isset($args['templates'])) {
            $ranks = array_column($after['templates'], 'rank', 'template');
            $templates = [];
            foreach (array_unique(array_map('intval', Values::items($args, 'templates'))) as $id) {
                if ($id <= 0) {
                    throw new Refusal('templates: id шаблонов больше 0');
                }
                $templates[] = ['template' => $id, 'rank' => (int)($ranks[$id] ?? 0)];
            }
            usort($templates, function (array $a, array $b) {
                return $a['template'] <=> $b['template'];
            });
            $after['templates'] = $templates;
        }
        return $after;
    }
}
