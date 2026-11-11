<?php
/**
 * Системные события mxAgentTools на install и upgrade (идемпотентно), снимаются при uninstall.
 *
 *   mxAgentToolsOnExternalIds — внешние коды модификаций для modification.export: параметры modifications
 *                               [mid => rid] и codes (\ArrayObject), плагин сайта кладёт $codes[$mid] = '<код>'.
 *
 * @var xPDOTransport $transport
 * @var array $options
 */
if ($transport->xpdo) {
    /** @var modX $modx */
    $modx =& $transport->xpdo;

    $events = ['mxAgentToolsOnExternalIds'];

    switch ($options[xPDOTransport::PACKAGE_ACTION]) {
        case xPDOTransport::ACTION_INSTALL:
        case xPDOTransport::ACTION_UPGRADE:
            foreach ($events as $name) {
                if ($modx->getObject('modEvent', ['name' => $name])) {
                    continue;
                }
                /** @var modEvent $event */
                $event = $modx->newObject('modEvent');
                $event->fromArray(['name' => $name, 'service' => 6, 'groupname' => 'mxagenttools'], '', true, true);
                $event->save();
                $modx->log(modX::LOG_LEVEL_INFO, '[mxagenttools] Зарегистрировано событие: ' . $name);
            }
            break;

        case xPDOTransport::ACTION_UNINSTALL:
            foreach ($events as $name) {
                if ($event = $modx->getObject('modEvent', ['name' => $name])) {
                    $event->remove();
                }
            }
            break;
    }
}

return true;
