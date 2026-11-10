<?php
/**
 * Таблицы пакета: mxagenttools_journal (журнал записей), mxagenttools_upload (принятые файлы), mxagenttools_selection
 * (зафиксированные выборки пакетных правок). createObjectContainer не трогает уже существующую таблицу — на upgrade
 * безопасен; колонки, добавленные в новых версиях (journal.batch, 1.1.0), доливаются addField/addIndex.
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
        foreach (['mxatJournal', 'mxatUpload', 'mxatSelection'] as $class) {
            $manager->createObjectContainer($class);
        }
        $table = $modx->getTableName('mxatJournal');
        $columns = [];
        $stmt = $modx->query("SHOW COLUMNS FROM {$table}");
        foreach ($stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [] as $column) {
            $columns[] = $column;
        }
        if ($columns && !in_array('batch', $columns, true)) {
            $manager->addField('mxatJournal', 'batch');
            $manager->addIndex('mxatJournal', 'batch');
        }
        break;
}

return true;
