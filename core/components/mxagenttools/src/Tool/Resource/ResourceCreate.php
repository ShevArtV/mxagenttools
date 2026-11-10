<?php

namespace MxAgentTools\Tool\Resource;

use MxAgentTools\Core\BindingGuard;
use MxAgentTools\Core\EntityRef;
use MxAgentTools\Core\Processor;
use MxAgentTools\Core\ProcessorInterface;
use MxAgentTools\Core\Refusal;
use MxAgentTools\Core\Values;
use MxAgentTools\Entity\Resource;
use MxAgentTools\Entity\ResourcePayload;
use MxAgentTools\Modx\TemplateVars;
use MxAgentTools\Tool\AbstractTool;
use MxAgentTools\Tool\WriteToolInterface;

/**
 * Создание ресурса процессором resource/create его класса (msProduct, msCategory, CollectionContainer — свои
 * процессоры пакета). Откат — в корзину MODX.
 */
class ResourceCreate extends AbstractTool implements WriteToolInterface
{
    const NAME = 'resource.create';
    const TITLE = 'Создать ресурс';
    const PATH = '/agent/resources';
    const KIND = self::WRITE;
    const DESCRIPTION = 'Создать ресурс: fields (pagetitle обязателен; parent, template, class_key — по умолчанию modDocument, '
        . 'context_key — как у родителя, alias, content, published…), tvs по имени (только TV шаблона), у товара — product, '
        . 'options, categories. Откат убирает ресурс в корзину.';

    /** @var \modX */
    private $modx;
    /** @var ProcessorInterface */
    private $processor;
    /** @var TemplateVars */
    private $tvs;
    /** @var ResourcePayload */
    private $payload;

    public function __construct(\modX $modx, ProcessorInterface $processor, TemplateVars $tvs, Resource $resource)
    {
        $this->modx = $modx;
        $this->processor = $processor;
        $this->tvs = $tvs;
        $this->payload = $resource->payload();
    }

    protected function args(): array
    {
        return self::obj([
            'fields' => self::map('поле => значение; pagetitle обязателен'),
            'tvs' => self::map('имя TV шаблона => значение'),
            'product' => self::map('поле товара ms2 => значение (class_key msProduct)'),
            'options' => self::map('опция товара ms2 => список значений'),
            'categories' => self::ids('доп. категории товара'),
        ], ['fields']);
    }

    public function targets(array $args): array
    {
        return [];
    }

    public function preview(array $args, array $before): array
    {
        return ['resource:new' => $this->target($args)];
    }

    public function apply(array $args): array
    {
        $target = $this->target($args);
        $data = $this->payload->build($target, []);
        $run = $this->processor->resource('resource/create', $data, $target['class_key']);
        $id = (int)($run['object']['id'] ?? 0);
        if (!$id) {
            throw new \RuntimeException('resource/create не вернул id');
        }
        return [new EntityRef('resource', $id)];
    }

    /** Снимок будущего ресурса из args; проверки — родитель, шаблон, класс, TV шаблона, привязки */
    private function target(array $args): array
    {
        $fields = Values::strMap(Values::map($args, 'fields'));
        if (trim($fields['pagetitle'] ?? '') === '') {
            throw new Refusal('fields.pagetitle обязателен');
        }
        $known = array_keys($this->modx->getFields('modResource'));
        foreach (array_keys($fields) as $k) {
            if (!in_array($k, $known, true) || in_array($k, array_merge(Resource::VOLATILE, ['deleted']), true)) {
                throw new Refusal("поле «{$k}» при создании не задаётся");
            }
        }
        $classKey = $fields['class_key'] ?? 'modDocument';
        if (!in_array($classKey, Processor::BASE_CLASSES, true) && !$this->modx->loadClass($classKey)) {
            throw new Refusal("класса ресурса {$classKey} на сайте нет (пакет не установлен)");
        }
        $parentId = (int)($fields['parent'] ?? 0);
        $parent = $parentId ? $this->modx->getObject('modResource', $parentId) : null;
        if ($parentId && !$parent) {
            throw new Refusal("родителя {$parentId} нет");
        }
        $fields['class_key'] = $classKey;
        $fields['parent'] = (string)$parentId;
        $fields['context_key'] = $fields['context_key'] ?? ($parent ? (string)$parent->get('context_key') : 'web');
        $fields['template'] = $fields['template'] ?? (string)$this->modx->getOption('default_template');
        if ((int)$fields['template'] && !$this->modx->getCount('modTemplate', ['id' => (int)$fields['template']])) {
            throw new Refusal("шаблона {$fields['template']} нет");
        }
        $tvs = [];
        foreach (Values::map($args, 'tvs') as $name => $v) {
            $tvs[$name] = is_array($v) ? implode('||', Values::strList($v)) : Values::str($v);
            BindingGuard::check("tvs.{$name}", $tvs[$name]);
        }
        $this->tvs->idsFor((int)$fields['template'], array_keys($tvs));
        $target = ['class_key' => $classKey, 'fields' => $fields, 'tvs' => $tvs];
        if ($classKey === 'msProduct') {
            $target['product'] = Values::map($args, 'product');
            $target['options'] = array_map([Values::class, 'strList'], Values::map($args, 'options'));
            $target['categories'] = array_map('intval', Values::items($args, 'categories'));
        } elseif (isset($args['product']) || isset($args['options']) || isset($args['categories'])) {
            throw new Refusal('product, options и categories — только для class_key msProduct');
        }
        return $target;
    }
}
