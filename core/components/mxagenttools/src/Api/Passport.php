<?php

namespace MxAgentTools\Api;

use MxAgentTools\Tool\ToolInterface;

/**
 * Паспорт эндпоинта mxApi из паспорта инструмента — без mxApi, чтобы проверяться тестами.
 * id — «agent.<инструмент>», чтение — GET со scope agent.read, запись — POST с mode/expect/note и scope agent.write.
 * Параметры, чьи имена есть в пути, помечаются in=path; остальные — query у чтения и body у записи.
 */
final class Passport
{
    const PROVIDER = 'mxagenttools';
    const PREFIX = 'agent.';
    const SCOPE_READ = 'agent.read';
    const SCOPE_WRITE = 'agent.write';
    const PERMISSION_READ = 'mxapi_agent_read';
    const PERMISSION_WRITE = 'mxapi_agent_write';

    const WRITE_NOTE = "\n\nЗапись в два шага. mode=plan (по умолчанию) ничего не пишет и отвечает targets, before, after "
        . '(предсказанное), diff — строки «объект поле: было → станет» — и hash. Покажите diff человеку; после его «да» тот же '
        . 'запрос с mode=apply и expect=hash: если объекты успели измениться — ошибка stale_plan, план надо показать заново. '
        . 'Ответ apply: journal (id записи журнала — для отката POST /agent/journal/{id}/rollback), diff, created, '
        . 'unexpected — где записалось не так, как в плане (плагины сайта, авто-псевдоним).';

    public static function forTool(ToolInterface $tool): array
    {
        $p = $tool->describe();
        $write = $tool->kind() === ToolInterface::WRITE;
        $parameters = (array)$p['parameters'];
        if ($write) {
            $parameters = array_merge($parameters, self::writeParameters());
        }
        return self::endpoint(self::PREFIX . $tool->name(), (string)$p['title'],
            (string)$p['description'] . ($write ? self::WRITE_NOTE : ''), (string)$p['path'], $write, $parameters);
    }

    /** Паспорт эндпоинта пакета: метод, scope, право, контекст mgr, параметры с in */
    public static function endpoint(string $id, string $title, string $description, string $path, bool $write, array $parameters): array
    {
        preg_match_all('/\{([a-z_]+)/', $path, $m);
        foreach ($parameters as $i => $param) {
            if (in_array($param['name'], $m[1], true)) {
                $parameters[$i]['in'] = 'path';
                $parameters[$i]['required'] = true;
            } elseif (!isset($param['in'])) {
                $parameters[$i]['in'] = $write ? 'body' : 'query';
            }
            if (empty($parameters[$i]['required'])) {
                unset($parameters[$i]['required']);
            }
        }
        return ['id' => $id, 'title' => $title, 'description' => $description, 'path' => $path,
            'methods' => [$write ? 'POST' : 'GET'], 'provider' => self::PROVIDER,
            'scope' => $write ? self::SCOPE_WRITE : self::SCOPE_READ,
            'permission' => $write ? self::PERMISSION_WRITE : self::PERMISSION_READ,
            'modx_context' => 'mgr', 'write' => $write, 'parameters' => array_values($parameters)];
    }

    public static function writeParameters(): array
    {
        return [
            ['name' => 'mode', 'type' => 'string', 'enum' => ['plan', 'apply'], 'default' => 'plan',
                'description' => 'plan — показать «было → станет» без записи; apply — записать (нужен expect).', 'example' => 'plan'],
            ['name' => 'expect', 'type' => 'string',
                'description' => 'hash из ответа plan — запись только если объекты с тех пор не менялись.',
                'example' => '3f786850e387550fdab836ed7e6dc881de23001b'],
            ['name' => 'note', 'type' => 'string',
                'description' => 'Кто и откуда попросил запись — уходит в журнал.', 'example' => 'Иван, чат «Контент»: поправить цену'],
        ];
    }
}
