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
    'mxagenttools.batch_limit' => ['value' => '500', 'xtype' => 'numberfield', 'area' => 'batch',
        'description' => 'Сколько объектов в одной порции пакетной правки (ids или page выборки). Объём операции не ограничен — порциями.'],
    'mxagenttools.selection_ttl' => ['value' => '86400', 'xtype' => 'numberfield', 'area' => 'batch',
        'description' => 'Сколько секунд живёт выборка resource.select / product.select.'],
    'mxagenttools.file_max_kb' => ['value' => '512', 'xtype' => 'numberfield', 'area' => 'code',
        'description' => 'Наибольший файл кода, который отдают file.read и element.get (статичные элементы), КиБ.'],
    'mxagenttools.image_max_side' => ['value' => '2000', 'xtype' => 'numberfield', 'area' => 'uploads',
        'description' => 'Картинка при приёме вписывается в этот размер по большей стороне, px (0 — не уменьшать), если max_width/max_height не переданы.'],
    'mxagenttools.image_quality' => ['value' => '82', 'xtype' => 'numberfield', 'area' => 'uploads',
        'description' => 'Качество JPEG/WebP при сжатии принятой картинки, 1–100.'],
    'mxagenttools.image_format' => ['value' => 'keep', 'xtype' => 'textfield', 'area' => 'uploads',
        'description' => 'Формат принятой картинки: keep — как прислана, jpeg, webp.'],
    'mxagenttools.image_strip_metadata' => ['value' => '1', 'xtype' => 'combo-boolean', 'area' => 'uploads',
        'description' => 'Снимать EXIF/GPS и комментарии с принятых картинок.'],
];

$settings = [];
foreach ($data as $key => $properties) {
    $setting = $this->modx->newObject('modSystemSetting');
    $setting->fromArray(array_merge(['key' => $key, 'namespace' => 'mxagenttools'], $properties), '', true, true);
    $settings[] = $setting;
}

return $settings;
