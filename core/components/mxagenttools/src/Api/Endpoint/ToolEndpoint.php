<?php

namespace MxAgentTools\Api\Endpoint;

use MxAgentTools\Api\Passport;
use MxAgentTools\Modx\Toolbox;
use MxAgentTools\Tool\ReadToolInterface;
use MxAgentTools\Tool\ToolInterface;
use MxAgentTools\Tool\WriteToolInterface;

/** Эндпоинт инструмента: паспорт — из инструмента, вызов — через Executor (read | plan | apply) */
class ToolEndpoint extends AgentEndpoint
{
    /** @var ToolInterface */
    private $tool;

    public function __construct(ToolInterface $tool, Toolbox $toolbox)
    {
        parent::__construct($toolbox);
        $this->tool = $tool;
    }

    protected function describe()
    {
        return Passport::forTool($this->tool);
    }

    protected function run(array $params): array
    {
        $executor = $this->toolbox->executor();
        if ($this->tool instanceof ReadToolInterface) {
            return $executor->read($this->tool, $params);
        }
        /** @var WriteToolInterface $tool */
        $tool = $this->tool;
        [$mode, $expect, $note] = self::writeMode($params);
        $args = self::toolArgs($params);
        return $mode === 'apply' ? $executor->apply($tool, $args, $expect, $note, mb_substr((string)($args['batch'] ?? ''), 0, 64))
            : $executor->plan($tool, $args);
    }
}
