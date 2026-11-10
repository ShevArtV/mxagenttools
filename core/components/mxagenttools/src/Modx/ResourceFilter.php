<?php

namespace MxAgentTools\Modx;

use MxAgentTools\Core\QueryGuard;
use MxAgentTools\Core\Refusal;
use MxAgentTools\Core\Values;

/**
 * Фильтр ресурсов для выборки пакетной правки: ids, parent (прямые дочерние), section (товары раздела: родитель
 * или доп. категория ms2), template, class_key, where (как у query, по полям modResource), deleted (0 — по
 * умолчанию, 1, any). Ответ — запрос id по возрастанию.
 */
final class ResourceFilter
{
    public static function args(bool $products): array
    {
        $out = [
            'ids' => ['type' => 'array', 'description' => 'id ресурсов списком', 'example' => [12, 15]],
            'parent' => ['type' => 'array', 'description' => 'id родителя (или список): прямые дочерние', 'example' => [3]],
            'template' => ['type' => 'array', 'description' => 'id шаблона (или список)', 'example' => [5]],
            'where' => ['type' => 'object', 'description' => 'условия xPDO по полям modResource, как у query',
                'example' => ['published' => 1, 'pagetitle:LIKE' => '%шёлк%']],
            'deleted' => ['type' => 'string', 'enum' => ['0', '1', 'any'], 'default' => '0',
                'description' => '0 — без корзины (по умолчанию), 1 — только в корзине, any — все'],
        ];
        if ($products) {
            $out['section'] = ['type' => 'integer', 'description' => 'раздел: его товары-дочерние и товары, где он — доп. категория',
                'example' => 3];
        } else {
            $out['class_key'] = ['type' => 'string', 'description' => 'класс ресурса', 'example' => 'modDocument'];
        }
        return $out;
    }

    public static function query(\modX $modx, array $args, bool $products): \xPDOQuery
    {
        $q = $modx->newQuery('modResource');
        $q->select($modx->escape('modResource') . '.' . $modx->escape('id'));
        $q->sortby($modx->escape('modResource') . '.' . $modx->escape('id'), 'ASC');
        $deleted = (string)($args['deleted'] ?? '0');
        if ($deleted !== 'any') {
            $q->where(['modResource.deleted' => $deleted === '1' ? 1 : 0]);
        }
        $any = false;
        foreach (['ids' => 'id', 'parent' => 'parent', 'template' => 'template'] as $arg => $field) {
            if (array_key_exists($arg, $args)) {
                $list = array_values(array_unique(array_map('intval', Values::items($args, $arg))));
                if (!$list) {
                    throw new Refusal("{$arg}: пустой список");
                }
                $q->where(["modResource.{$field}:IN" => $list]);
                $any = true;
            }
        }
        if ($products) {
            $q->where(['modResource.class_key' => 'msProduct']);
            if (!empty($args['section'])) {
                $section = (int)$args['section'];
                $members = $modx->getTableName('msCategoryMember');
                $q->where("(`modResource`.`parent` = {$section} OR `modResource`.`id` IN "
                    . "(SELECT `product_id` FROM {$members} WHERE `category_id` = {$section}))");
                $any = true;
            }
        } elseif (Values::text($args, 'class_key') !== '') {
            $q->where(['modResource.class_key' => Values::text($args, 'class_key')]);
        }
        $where = Values::map($args, 'where');
        if ($where) {
            $fields = array_keys((array)$modx->getFields('modResource'));
            $q->where(QueryGuard::where('modResource', $fields, $where));
            $any = true;
        }
        if (!$any) {
            throw new Refusal('фильтр пуст: нужен хотя бы один из ids, parent, template, '
                . ($products ? 'section, ' : '') . 'where');
        }
        return $q;
    }
}
