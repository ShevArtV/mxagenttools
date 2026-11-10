<?php

namespace MxAgentTools\Tool\Modification;

use MxAgentTools\Core\EntityRef;
use MxAgentTools\Core\Values;

class ModificationUpdate extends ModificationWrite
{
    const NAME = 'modification.update';
    const TITLE = 'Изменить модификацию';
    const PATH = '/agent/modifications/{id:\\d+}';
    const DESCRIPTION = 'Поля модификации (modification/update: price, old_price, count, active, name, article, weight, '
        . 'type, rank) и опции целиком {ключ: [значения]} (изменённый ключ снимается и пишется option/create — новое '
        . 'значение попадает в опции товара ms2). Фото — modification.images.';

    protected function args(): array
    {
        return self::obj([
            'id' => self::int('id модификации', 7),
            'fields' => self::map('поле => значение', ['price' => 2490, 'active' => 0]),
            'options' => self::map('все опции модификации {ключ: значение|[значения]}', ['color' => 'синий', 'size' => 'L']),
        ], ['id']);
    }

    public function targets(array $args): array
    {
        $refs = parent::targets($args);
        if (isset($args['options'])) {
            $refs[] = new EntityRef('resource', $this->current($args)['rid']);
        }
        return $refs;
    }

    public function preview(array $args, array $before): array
    {
        if (!isset($args['fields']) && !isset($args['options'])) {
            throw new \MxAgentTools\Core\Refusal('нечего менять: нужны fields или options');
        }
        $mod = $this->before($args, $before);
        $after = ['modification:' . $mod['id'] => ModificationChange::merge($mod, array_intersect_key($args, ['fields' => 1, 'options' => 1]))];
        if (isset($before['resource:' . $mod['rid']])) {
            // значения новых опций добавятся в опции товара; точный состав покажет unexpected после записи
            $after['resource:' . $mod['rid']] = $before['resource:' . $mod['rid']];
        }
        return $after;
    }

    public function apply(array $args): array
    {
        $current = $this->current($args);
        $after = ModificationChange::merge($current, array_intersect_key($args, ['fields' => 1, 'options' => 1]));
        if (Values::map($args, 'fields')) {
            $this->mods->update($current['id'], $after['fields']);
        }
        if (isset($args['options'])) {
            $this->mods->setOptions($current['id'], $current['rid'], $after['options']);
        }
        $this->files->refresh();
        return [];
    }
}
