<?php
/**
 * Регистрация эндпоинтов mxAgentTools в mxApi. Событие: mxApiOnRegisterEndpoints.
 * Плагин ставит и снимает сам пакет: отключить инструменты агента — отключить плагин.
 * Новый плагин подхватывается только после сброса кэша (eventMap).
 *
 * @var modX $modx
 */

if ($modx->event->name !== 'mxApiOnRegisterEndpoints') {
    return;
}

$corePath = $modx->getOption('mxagenttools.core_path', null, $modx->getOption('core_path') . 'components/mxagenttools/');
$bootstrap = $corePath . 'src/bootstrap.php';
if (!is_readable($bootstrap)) {
    $modx->log(modX::LOG_LEVEL_ERROR, '[mxagenttools] Не найден загрузчик провайдера: ' . $bootstrap);

    return;
}

$modx->event->output(include $bootstrap);
