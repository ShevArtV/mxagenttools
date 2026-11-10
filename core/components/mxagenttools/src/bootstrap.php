<?php
/**
 * Загрузчик провайдера mxAgentTools. Вызывается из плагина на mxApiOnRegisterEndpoints: к этому моменту
 * автозагрузка mxApi подключена, классы MxApi\* доступны. MODX 2 классы пакета не автозагружает — их
 * подключает autoload.php пакета (PSR-4 MxAgentTools\ → src/).
 *
 * Возвращает имя класса провайдера строкой: modSystemEvent::output() склеивает значения обработчиков в строку,
 * объект туда передавать нельзя.
 *
 * @return string
 */

require_once dirname(__DIR__) . '/autoload.php';

return 'MxAgentTools\\Api\\Provider';
