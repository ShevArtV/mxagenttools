<?php

namespace MxAgentTools\Tool\Batch;

use MxAgentTools\Core\Refusal;
use MxAgentTools\Core\Values;
use MxAgentTools\Tool\Resource\ResourceChange;

class ResourceBatch extends ResourceBatchTool
{
    const NAME = 'resource.batch';
    const TITLE = 'Массовая правка ресурсов';
    const PATH = '/agent/resources/batch';
    const DESCRIPTION = 'Одна и та же правка многих ресурсов одним планом: template, parent, поля (fields) и TV (tvs) — '
        . 'значения целиком, как у resource.update. Объекты — ids (мелкая правка) или selection + page из resource.select / '
        . 'product.select (любой объём: порция = mxagenttools.batch_limit объектов, порции — page 0 … pages-1, один batch '
        . 'на операцию). План: total, changed, unchanged, summary (поле → сколько объектов), diff первых 20 объектов, more. '
        . 'TV должен быть привязан к текущему шаблону ресурса; смена шаблона и TV нового шаблона — двумя операциями.';

    protected function changeArgs(): array
    {
        return [
            'template' => self::int('новый шаблон всех ресурсов', 6),
            'parent' => self::int('новый родитель всех ресурсов', 13),
            'fields' => self::map('поле => значение', ['published' => 1]),
            'tvs' => self::map('имя TV => значение', ['seo_title' => '…']),
        ];
    }

    protected function change(array $snapshot, array $args): array
    {
        $fields = Values::map($args, 'fields');
        foreach (['template', 'parent'] as $k) {
            if (isset($args[$k]) && $args[$k] !== '') {
                $fields[$k] = (int)$args[$k];
            }
        }
        $change = array_filter(['fields' => $fields, 'tvs' => Values::map($args, 'tvs')]);
        if (!$change) {
            throw new Refusal('нечего менять: template, parent, fields или tvs');
        }
        try {
            return ResourceChange::merge($snapshot, $change);
        } catch (Refusal $e) {
            throw new Refusal("ресурс {$snapshot['id']}: " . $e->getMessage());
        }
    }
}
