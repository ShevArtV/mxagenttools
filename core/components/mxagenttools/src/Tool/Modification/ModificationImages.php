<?php

namespace MxAgentTools\Tool\Modification;

use MxAgentTools\Core\Values;

class ModificationImages extends ModificationWrite
{
    const NAME = 'modification.images';
    const TITLE = 'Фото модификации';
    const PATH = '/agent/modifications/{id:\\d+}/images';
    const DESCRIPTION = 'Фото модификации целиком по порядку: images — id фото из галереи товара, первое получает rank 0 и '
        . 'становится фото модификации; [] — снять все. Пишется процессором misc/gallery/update (снять все, добавить по порядку).';

    protected function args(): array
    {
        return self::obj(['id' => self::int('id модификации', 7), 'images' => self::ids('id фото галереи по порядку', [102, 101])],
            ['id']);
    }

    public function preview(array $args, array $before): array
    {
        $mod = $this->before($args, $before);
        $this->checkImages($mod['rid'], Values::items($args, 'images'));
        return ['modification:' . $mod['id'] => ModificationChange::merge($mod, ['images' => Values::items($args, 'images')])];
    }

    public function apply(array $args): array
    {
        $current = $this->current($args);
        $this->checkImages($current['rid'], Values::items($args, 'images'));
        $after = ModificationChange::merge($current, ['images' => Values::items($args, 'images')]);
        $this->mods->setImages($current['id'], array_column($after['images'], 0));
        $this->files->refresh();
        return [];
    }
}
