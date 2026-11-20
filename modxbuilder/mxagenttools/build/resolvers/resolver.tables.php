<?php
/**
 * Таблицы пакета: mxagenttools_journal (журнал записей), mxagenttools_upload (принятые файлы), mxagenttools_selection
 * (зафиксированные выборки пакетных правок). createObjectContainer не трогает уже существующую таблицу — на upgrade
 * безопасен; колонки, добавленные в новых версиях (journal.batch, 1.1.0), доливаются addField/addIndex.
 * xPDO 2.8 берёт из tableMeta только engine, charset таблицы не задаёт — она наследует кодировку БД (latin1 на старых
 * сайтах, и журнал теряет кириллицу). Поэтому после createObjectContainer каждая таблица приводится к
 * utf8mb4_unicode_ci явным ALTER ... CONVERT; уже верные пропускаются (повторный запуск ничего не меняет).
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
        $classes = ['mxatJournal', 'mxatUpload', 'mxatSelection'];
        foreach ($classes as $class) {
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

        $collation = 'utf8mb4_unicode_ci';
        foreach ($classes as $class) {
            $name = $modx->getTableName($class);
            // collation таблицы и всех текстовых колонок (колонки могут отличаться от таблицы после ручного ALTER)
            $stmt = $modx->query('SHOW TABLE STATUS LIKE ' . $modx->quote(str_replace(['`', '_'], ['', '\\_'], $name)));
            $status = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
            if (!$status) {
                continue;
            }
            $collations = [(string)$status['Collation']];
            $stmt = $modx->query("SHOW FULL COLUMNS FROM {$name}");
            foreach ($stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [] as $col) {
                if ($col['Collation'] !== null) {
                    $collations[] = (string)$col['Collation'];
                }
            }
            $collations = array_values(array_unique($collations));
            if ($collations === [$collation]) {
                $modx->log(modX::LOG_LEVEL_INFO, "[mxagenttools] Таблица {$name}: {$collation}, пропуск.");
                continue;
            }
            // PDO у MODX в режиме ERRMODE_SILENT: ошибка ALTER приходит как false, а не исключением
            if ($modx->exec("ALTER TABLE {$name} CONVERT TO CHARACTER SET utf8mb4 COLLATE {$collation}") === false) {
                $info = $modx->errorInfo();
                $modx->log(modX::LOG_LEVEL_ERROR, "[mxagenttools] Не удалось перевести {$name} в utf8mb4: " . ($info[2] ?? ''));
            } else {
                $modx->log(modX::LOG_LEVEL_INFO, "[mxagenttools] Таблица {$name} переведена в {$collation} (было: " . implode(',', $collations) . ').');
            }
        }
        break;
}

return true;
