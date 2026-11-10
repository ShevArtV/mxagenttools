<?php

return [
    'file' => [
        [
            'source' => $this->config['source_core'],
            'target' => "return MODX_CORE_PATH . 'components/';",
        ],
    ],
    'php' => [
        ['source' => $this->config['resolvers'] . 'resolver.tables.php'],
        // права эндпоинтов в шаблоне mxApi и политика пакета
        ['source' => $this->config['resolvers'] . 'resolver.acl.php'],
    ],
];
