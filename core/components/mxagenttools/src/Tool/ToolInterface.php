<?php

namespace MxAgentTools\Tool;

/**
 * Инструмент агента — из его паспорта строится эндпоинт mxApi (Api\Endpoint\ToolEndpoint): name — id без
 * префикса «agent.», kind — read (GET) | write (POST с mode=plan|apply), describe — title, description, path
 * (относительно префикса mxApi, параметры пути — {имя}) и parameters в формате mxApi. Это и есть документация
 * для агента: он читает её из /meta/openapi.
 */
interface ToolInterface
{
    const READ = 'read';
    const WRITE = 'write';

    public function name(): string;

    public function kind(): string;

    /** @return array {title, description, path, parameters: [{name, type, required?, description, example?}]} */
    public function describe(): array;
}
