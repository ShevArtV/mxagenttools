<?php

namespace MxAgentTools\Api\Endpoint;

use MxAgentTools\Core\Refusal;
use MxAgentTools\Core\Secrets;
use MxAgentTools\Modx\Toolbox;
use MxApi\Core\Endpoint\AbstractEndpoint;
use MxApi\Core\Endpoint\EndpointContext;
use MxApi\Core\Http\ApiException;
use MxApi\Core\Http\Request;
use MxApi\Core\Http\Response;

/**
 * База эндпоинтов пакета: только объявленные параметры (readParams), модели пакетов до вызова, ответ — data.
 * Отказ (Refusal) — ошибка mxApi с кодом refusal|stale_plan и текстом для человека; ошибка процессора MODX —
 * processor_error; прочее — internal_error штатно. Пользователь MODX — владелец токена: mxApi ставит его
 * в $modx->user до вызова, поэтому процессоры проверяют его права и пишут его в «Кем изменено».
 */
abstract class AgentEndpoint extends AbstractEndpoint
{
    /** @var Toolbox */
    protected $toolbox;

    public function __construct(Toolbox $toolbox)
    {
        $this->toolbox = $toolbox;
    }

    public function handle(Request $request, EndpointContext $context)
    {
        $params = $this->readParams($request);
        $this->toolbox->prepare();
        try {
            // значения настроек-секретов в снимках и diff — маской (Core\Secrets); hash уже посчитан по настоящим
            return Response::success(Secrets::output($this->run($params)));
        } catch (Refusal $e) {
            throw new ApiException($e->code(), $e->getMessage(), $e->code() === Refusal::STALE_PLAN ? 409 : 422, $e->details());
        } catch (\RuntimeException $e) {
            throw new ApiException('processor_error', $e->getMessage(), 400);
        }
    }

    abstract protected function run(array $params): array;

    /** mode, expect, note записи; apply без expect — отказ до любой работы */
    protected static function writeMode(array $params): array
    {
        $mode = (string)($params['mode'] ?? 'plan');
        $expect = (string)($params['expect'] ?? '');
        if ($mode === 'apply' && !preg_match('/^[0-9a-f]{40}$/', $expect)) {
            throw new Refusal('mode=apply: нужен expect — hash из ответа mode=plan');
        }
        return [$mode, $expect, mb_substr((string)($params['note'] ?? ''), 0, 500)];
    }

    /** Параметры инструмента — без служебных mode/expect/note */
    protected static function toolArgs(array $params): array
    {
        unset($params['mode'], $params['expect'], $params['note']);
        return $params;
    }
}
