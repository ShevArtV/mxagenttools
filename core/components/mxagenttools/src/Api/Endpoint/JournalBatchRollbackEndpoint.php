<?php

namespace MxAgentTools\Api\Endpoint;

use MxAgentTools\Api\Passport;

class JournalBatchRollbackEndpoint extends AgentEndpoint
{
    protected function describe()
    {
        $params = array_filter(Passport::writeParameters(), function ($p) {
            return $p['name'] !== 'batch';
        });
        return Passport::endpoint('agent.journal.batch.rollback', 'Откатить пакетную операцию',
            'Вернуть все объекты пакетной операции (все записи журнала с этим batch — порции product.prices, resource.batch, '
            . 'product.categories) к состоянию до неё: порции откатываются в обратном порядке. mode=plan — сводка: entries '
            . '(журнал, changed, diverged, objects), total, changed, summary, diff первых объектов, divergence — если после '
            . 'бота меняли, и hash; mode=apply с expect=hash — откат; сбой посередине — complete: false, done (откаченные '
            . 'порции), failed, error, left: повтор отката продолжит с оставшихся.' . Passport::WRITE_NOTE,
            '/agent/journal/batch/{batch:[A-Za-z0-9_.-]+}/rollback', true, array_merge([
                ['name' => 'batch', 'type' => 'string', 'description' => 'id пакетной операции (batch записей журнала).',
                    'example' => 'chat30605-plan-a1b2c3d4'],
            ], array_values($params)));
    }

    protected function run(array $params): array
    {
        [$mode, $expect, $note] = self::writeMode($params);
        $rollback = $this->toolbox->batchRollback();
        return $mode === 'apply' ? $rollback->apply((string)$params['batch'], $expect, $note) : $rollback->plan((string)$params['batch']);
    }
}
