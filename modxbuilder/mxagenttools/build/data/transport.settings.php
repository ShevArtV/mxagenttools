<?php
/**
 * Настройки пакета. Конфига на сайте нет: всё о сайте — в документации сайта для агента.
 *
 * @var modxBuilder $this
 */

$data = [
    'mxagenttools.core_path' => ['value' => '{core_path}components/mxagenttools/', 'xtype' => 'textfield', 'area' => 'paths',
        'description' => 'Каталог пакета mxAgentTools.'],
    'mxagenttools.upload_max_mb' => ['value' => '20', 'xtype' => 'numberfield', 'area' => 'uploads',
        'description' => 'Наибольший размер файла, принимаемого POST /agent/uploads, МиБ.'],
    'mxagenttools.upload_ttl' => ['value' => '3600', 'xtype' => 'numberfield', 'area' => 'uploads',
        'description' => 'Сколько секунд живёт принятый файл (token) до записи.'],
    'mxagenttools.journal_depth' => ['value' => '5000', 'xtype' => 'numberfield', 'area' => 'journal',
        'description' => 'Сколько последних записей журнала хранить (0 — все). Откатить можно только запись из журнала.'],
];

$settings = [];
foreach ($data as $key => $properties) {
    $setting = $this->modx->newObject('modSystemSetting');
    $setting->fromArray(array_merge(['key' => $key, 'namespace' => 'mxagenttools'], $properties), '', true, true);
    $settings[] = $setting;
}

return $settings;
