<?php

namespace MxAgentTools\Api\Endpoint;

use MxAgentTools\Api\Passport;
use MxAgentTools\Core\Diff;

class JournalGetEndpoint extends AgentEndpoint
{
    protected function describe()
    {
        return Passport::endpoint('agent.journal.get', 'Запись журнала',
            'Запись журнала целиком: params запроса, before и after (снимки объектов), predicted (план), created, diff '
            . '«было → стало», статус и ошибка, кто, когда, note. Копии файлов (kept) — только перечнем.',
            '/agent/journal/{id:\d+}', false, [
                ['name' => 'id', 'type' => 'integer', 'description' => 'id записи журнала (из ответа apply).', 'example' => 42],
            ]);
    }

    protected function run(array $params): array
    {
        $entry = $this->toolbox->journal()->read((int)$params['id']);
        $entry['diff'] = Diff::lines((array)$entry['before'], (array)($entry['after'] ?? []));
        foreach ((array)$entry['kept'] as $key => $files) {
            $entry['kept'][$key] = array_map(function ($file) {
                return is_array($file) ? (string)($file['name'] ?? '') : '';
            }, (array)$files);
        }
        return $entry;
    }
}
