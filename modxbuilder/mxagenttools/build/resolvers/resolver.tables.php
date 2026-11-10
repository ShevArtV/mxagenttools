<?php
/**
 * Таблицы пакета: mxagenttools_journal (журнал записей) и mxagenttools_upload (принятые файлы).
 * createObjectContainer не трогает уже существующую таблицу — на upgrade безопасен.
 *
 * @var xPDOTransport $transport
 * @var array $options
 */
if (!$transport->xpdo || !($transport instanceof xPDOTransport)) {
    return false;
}

/** @var modX $modx */
$modx = $transport->xpdo;
$modelPath = $modx->getOption('mxagenttools.core_path', null, MODX_CORE_PATH . 'components/mxagenttools/') . 'model/';

switch ($options[xPDOTransport::PACKAGE_ACTION]) {
    case xPDOTransport::ACTION_INSTALL:
    case xPDOTransport::ACTION_UPGRADE:
        $modx->addPackage('mxagenttools', $modelPath);
        $manager = $modx->getManager();
        foreach (['mxatJournal', 'mxatUpload'] as $class) {
            $manager->createObjectContainer($class);
        }
        break;
}

return true;
