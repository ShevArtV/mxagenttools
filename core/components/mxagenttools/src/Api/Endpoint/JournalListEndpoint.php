<?php

namespace MxAgentTools\Api\Endpoint;

use MxAgentTools\Api\Passport;

class JournalListEndpoint extends AgentEndpoint
{
    protected function describe()
    {
        return Passport::endpoint('agent.journal.list', 'Журнал записей',
            'Последние записи инструментов агента, новые первыми: id, endpoint, kind (apply | rollback), status (started — '
            . 'запись идёт или оборвалась, done, failed), targets, кто (username) и note, когда (at), rolled_back — id записи '
            . 'отката, если её откатили, rollback_of — что откатывала эта запись, batch — пакетная операция.', '/agent/journal', false, [
                ['name' => 'limit', 'type' => 'integer', 'default' => 20, 'min' => 1, 'max' => 100,
                    'description' => 'Сколько записей вернуть (до 100).', 'example' => 20],
                ['name' => 'offset', 'type' => 'integer', 'default' => 0, 'min' => 0, 'description' => 'Сдвиг от новых.'],
                ['name' => 'batch', 'type' => 'string', 'description' => 'Только записи пакетной операции (batch из записи).',
                    'example' => 'chat30605-plan-a1b2c3d4'],
            ]);
    }

    protected function run(array $params): array
    {
        return ['journal' => $this->toolbox->journal()->recent((int)($params['limit'] ?? 20), (int)($params['offset'] ?? 0),
            (string)($params['batch'] ?? ''))];
    }
}
