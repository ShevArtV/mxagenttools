<?php

namespace MxAgentTools\Api;

use MxAgentTools\Api\Endpoint\JournalGetEndpoint;
use MxAgentTools\Api\Endpoint\JournalListEndpoint;
use MxAgentTools\Api\Endpoint\JournalRollbackEndpoint;
use MxAgentTools\Api\Endpoint\ToolEndpoint;
use MxAgentTools\Api\Endpoint\UploadEndpoint;
use MxAgentTools\Modx\Toolbox;
use MxApi\Core\Config;
use MxApi\Core\Platform\PlatformInterface;
use MxApi\Core\Provider\ProviderInterface;

/**
 * Эндпоинты mxAgentTools в mxApi: по эндпоинту на инструмент (agent.<инструмент>), журнал, откат и приём файлов.
 * Инструменты пакетов-расширений (ms2, msOptionsPrice2, ClientConfig) — только если пакет установлен.
 */
class Provider implements ProviderInterface
{
    public function getId()
    {
        return Passport::PROVIDER;
    }

    /** Нужен MODX 2: инструменты работают с modX напрямую */
    public function isAvailable(PlatformInterface $platform)
    {
        return method_exists($platform, 'getModx') && class_exists('modX', false);
    }

    public function getEndpoints(PlatformInterface $platform, Config $config)
    {
        /** @var \MxApi\Platform\Modx2\Modx2Platform $platform */
        $toolbox = new Toolbox($platform->getModx());
        $endpoints = [];
        foreach ($toolbox->tools() as $tool) {
            $endpoints[] = new ToolEndpoint($tool, $toolbox);
        }
        array_push($endpoints, new JournalListEndpoint($toolbox), new JournalGetEndpoint($toolbox),
            new JournalRollbackEndpoint($toolbox), new UploadEndpoint($toolbox));
        return $endpoints;
    }
}
