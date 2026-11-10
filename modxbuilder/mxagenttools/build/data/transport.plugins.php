<?php
/**
 * Плагин регистрации эндпоинтов в mxApi. Статичный: код берётся из файла пакета, правка кода не требует
 * пересоздания элемента в базе.
 *
 * @var modxBuilder $this
 */

$spec = [
    'name' => 'mxAgentToolsApi',
    'description' => 'Регистрирует эндпоинты инструментов агента (agent.*) в mxApi.',
    'file' => 'plugin.mxagenttools.php',
    'static_file' => 'mxagenttools/elements/plugins/plugin.mxagenttools.php',
    'events' => ['mxApiOnRegisterEndpoints'],
];

$file = $this->config['source_core'] . 'elements/plugins/' . $spec['file'];
if (!is_readable($file)) {
    $this->modx->log(modX::LOG_LEVEL_ERROR, '[mxagenttools] Не найден файл плагина: ' . $file);

    return [];
}

/** @var modPlugin $plugin */
$plugin = $this->modx->newObject('modPlugin');
$plugin->fromArray([
    'name' => $spec['name'],
    'description' => $spec['description'],
    'static' => 1,
    'static_file' => $spec['static_file'],
    'source' => 0,
    'disabled' => 0,
], '', true, true);

$events = [];
foreach ($spec['events'] as $event) {
    /** @var modPluginEvent $pe */
    $pe = $this->modx->newObject('modPluginEvent');
    $pe->fromArray(['event' => $event, 'priority' => 0, 'propertyset' => 0], '', true, true);
    $events[] = $pe;
}
$plugin->addMany($events, 'PluginEvents');

return [$plugin];
