<?php

namespace MxAgentTools\Tool\Code;

use MxAgentTools\Core\Refusal;

/** Виды элементов-кода: класс xPDO, поле имени, поле кода */
final class Elements
{
    const TYPES = [
        'chunk' => ['modChunk', 'name', 'snippet'],
        'template' => ['modTemplate', 'templatename', 'content'],
        'snippet' => ['modSnippet', 'name', 'snippet'],
        'plugin' => ['modPlugin', 'name', 'plugincode'],
    ];

    public static function spec(string $type): array
    {
        if (!isset(self::TYPES[$type])) {
            throw new Refusal('type: ' . implode(' | ', array_keys(self::TYPES)));
        }
        return self::TYPES[$type];
    }
}
