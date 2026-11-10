<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';

// mxApi для проверки паспортов эндпоинтов — необязательно: MXAPI_AUTOLOAD или соседний checkout mxapi2
$mxapi = getenv('MXAPI_AUTOLOAD') ?: dirname(__DIR__, 2) . '/mxapi2/core/components/mxapi/vendor/autoload.php';
if (is_file($mxapi)) {
    require_once $mxapi;
}
