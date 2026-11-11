<?php

namespace MxAgentTools\Api\Endpoint;

use MxAgentTools\Api\Passport;
use MxAgentTools\Core\Refusal;
use MxAgentTools\Modx\Toolbox;
use MxAgentTools\Tool\BatchToolInterface;
use MxAgentTools\Tool\NoteRequiredInterface;
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
        if ($mode === 'apply' && $tool instanceof NoteRequiredInterface && trim($note) === '') {
            throw new Refusal('mode=apply: нужен note — кто просит и что меняем (попадает в журнал)');
        }
        $args = self::toolArgs($params);
        $out = $mode === 'apply' ? $executor->apply($tool, $args, $expect, $note, mb_substr((string)($args['batch'] ?? ''), 0, 64))
            : $executor->plan($tool, $args);
        if ($tool instanceof BatchToolInterface && !empty($args['selection'])) {
            $out['selection'] = $this->toolbox->selectionInfo((string)$args['selection'], (int)($args['page'] ?? 0));
        }
        return $out;
    }
}
