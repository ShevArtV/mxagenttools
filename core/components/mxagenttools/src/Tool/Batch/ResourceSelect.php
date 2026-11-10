<?php

namespace MxAgentTools\Tool\Batch;

class ResourceSelect extends SelectTool
{
    const NAME = 'resource.select';
    const TITLE = 'Выборка ресурсов для пакетной правки';
    const PATH = '/agent/resources/select';
    const DESCRIPTION = 'Первый шаг массовой правки ресурсов (resource.batch): фильтр (ids, parent, template, class_key, where, '
        . 'deleted) фиксируется на сайте выборкой — список id не меняется, даже если правка сменит шаблон или родителя. '
        . 'Ответ: selection, total, pages — сколько порций по page_size (mxagenttools.batch_limit), first — первые 20 id, '
        . 'expires. Дальше пакетный инструмент с selection и page = 0 … pages-1, одним batch на всю операцию.';
}
