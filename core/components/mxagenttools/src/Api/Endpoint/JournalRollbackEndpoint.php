<?php

namespace MxAgentTools\Api\Endpoint;

use MxAgentTools\Api\Passport;

class JournalRollbackEndpoint extends AgentEndpoint
{
    protected function describe()
    {
        return Passport::endpoint('agent.journal.rollback', 'Откатить запись',
            'Вернуть объекты записи журнала к состоянию до неё: созданное удаляется (ресурс — в корзину), изменённое '
            . 'и удалённое возвращается (удалённое фото, TV, модификация — заново, с новым id). Тот же порядок, что у записи: '
            . 'mode=plan показывает before (как сейчас), after (как станет), diff и hash; если после записи объекты меняли — '
            . 'diverged: true и divergence — строки «у бота → сейчас → станет»: покажите их человеку. mode=apply с expect=hash '
            . 'откатывает (и поверх чужих правок — осознанно). Откат сам пишется в журнал, и его тоже можно откатить.'
            . Passport::WRITE_NOTE, '/agent/journal/{id:\d+}/rollback', true, array_merge([
                ['name' => 'id', 'type' => 'integer', 'description' => 'id записи журнала, которую откатить.', 'example' => 42],
            ], Passport::writeParameters()));
    }

    protected function run(array $params): array
    {
        [$mode, $expect, $note] = self::writeMode($params);
        $executor = $this->toolbox->executor();
        return $mode === 'apply' ? $executor->rollbackApply((int)$params['id'], $expect, $note)
            : $executor->rollbackPlan((int)$params['id']);
    }
}
