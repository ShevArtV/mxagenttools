<?php

namespace MxAgentTools\Tool;

/**
 * Пакетная правка: много объектов одной записью журнала. Executor отвечает на неё сводкой (Core\BatchView), а не
 * снимками целиком. Объекты — явный ids или порция page зафиксированной выборки selection (Tool\Batch\BatchIds).
 */
interface BatchToolInterface extends WriteToolInterface
{
}
