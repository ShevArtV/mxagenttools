<?php

namespace MxAgentTools\Tool;

interface ReadToolInterface extends ToolInterface
{
    public function read(array $args): array;
}
