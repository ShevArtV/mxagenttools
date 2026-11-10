<?php

namespace MxAgentTools\Tool\Modification;

class ModificationDelete extends ModificationWrite
{
    const NAME = 'modification.delete';
    const TITLE = 'Удалить модификацию';
    const PATH = '/agent/modifications/{id:\\d+}/delete';
    const DESCRIPTION = 'Удалить модификацию (modification/remove, опции и привязки фото — каскадом). Откат создаёт её заново '
        . 'с теми же полями, опциями и фото (новый id).';

    protected function args(): array
    {
        return self::obj(['id' => self::int('id модификации', 7)], ['id']);
    }

    public function preview(array $args, array $before): array
    {
        return ['modification:' . $this->before($args, $before)['id'] => null];
    }

    public function apply(array $args): array
    {
        $this->mods->remove($this->current($args)['id']);
        $this->files->refresh();
        return [];
    }
}
