<?php

define('COMPONENT_BUILD', false);
$root = dirname(__DIR__, 4) . '/';
$builderRoot = $root . 'modxbuilder/';

$buildConfig = [
    'real_package_name' => 'mxAgentTools',
    'package_name' => 'mxagenttools',
    'package_version' => '1.1.0',
    'package_release' => 'beta12',
    'package_table_prefix' => 'mxagenttools_',
    'package_class_prefix' => 'mxat',
    // модель (schema + классы) лежит в репо готовой: core/components/mxagenttools/model/
    'regenerate_schema' => false,
    'regenerate_classes' => false,
    'regenerate_maps' => false,
    'modx_root' => $root,
    'builder_root' => $builderRoot,
    'tools_root' => $builderRoot . 'tools/',
];

$componentRoot = $builderRoot . 'mxagenttools/';
$buildConfig = array_merge($buildConfig, [
    'root' => $root,
    'build' => $componentRoot . 'build/',
    'resolvers' => $componentRoot . 'build/resolvers/',
    'data' => $componentRoot . 'build/data/',
    'source_core' => $root . 'core/components/mxagenttools/',
    'source_assets' => $root . 'assets/components/mxagenttools/',
    'source_docs' => $root . 'core/components/mxagenttools/docs/',
]);

define('MODX_CORE_PATH', $root . 'core/');
define('MODX_BASE_PATH', $root);
define('MODX_BASE_URL', '/');

unset($root, $builderRoot, $componentRoot);
return $buildConfig;
