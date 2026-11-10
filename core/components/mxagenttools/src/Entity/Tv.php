<?php

namespace MxAgentTools\Entity;

use MxAgentTools\Core\Canon;
use MxAgentTools\Core\IdMap;
use MxAgentTools\Core\ProcessorInterface;
use MxAgentTools\Core\Values;
use MxAgentTools\Modx\TemplateVars;

/**
 * TV: поля, параметры ввода и вывода, привязка к шаблонам (с rank) и все значения у ресурсов — чтобы откат
 * удаления вернул TV целиком. Поля static/static_file/source (TV из файла) инструменты не трогают: это код.
 */
class Tv implements EntityInterface
{
    const FIELDS = ['name', 'caption', 'description', 'type', 'default_text', 'elements', 'display', 'category', 'rank', 'locked'];

    /** @var \modX */
    private $modx;
    /** @var ProcessorInterface */
    private $processor;
    /** @var TemplateVars */
    private $tvs;
    /** @var IdMap */
    private $ids;

    public function __construct(\modX $modx, ProcessorInterface $processor, TemplateVars $tvs, IdMap $ids)
    {
        $this->modx = $modx;
        $this->processor = $processor;
        $this->tvs = $tvs;
        $this->ids = $ids;
    }

    public function type(): string
    {
        return 'tv';
    }

    public function snapshot($id): ?array
    {
        $tv = $this->tvs->find((int)$id);
        if (!$tv) {
            return null;
        }
        $fields = [];
        foreach (self::FIELDS as $k) {
            $fields[$k] = Values::str($tv->get($k));
        }
        $templates = [];
        $q = $this->modx->newQuery('modTemplateVarTemplate', ['tmplvarid' => (int)$id]);
        $q->sortby('templateid');
        foreach ($this->modx->getIterator('modTemplateVarTemplate', $q, false) as $link) {
            $templates[] = ['template' => (int)$link->get('templateid'), 'rank' => (int)$link->get('rank')];
        }
        $values = [];
        $q = $this->modx->newQuery('modTemplateVarResource', ['tmplvarid' => (int)$id]);
        $q->sortby('contentid');
        foreach ($this->modx->getIterator('modTemplateVarResource', $q, false) as $row) {
            $values[(string)$row->get('contentid')] = (string)$row->get('value');
        }
        return ['id' => (int)$id, 'fields' => $fields,
            'input_properties' => Values::strMap((array)$tv->get('input_properties')),
            'output_properties' => Values::strMap((array)$tv->get('output_properties')),
            'templates' => $templates, 'values' => $values];
    }

    /** TV нет (удалили) — создаётся заново под новым id (замена — в IdMap), значения возвращаются построчно */
    public function restore(array $snapshot): void
    {
        $old = (int)$snapshot['id'];
        $current = $this->snapshot($this->ids->map('modTemplateVar', $old));
        $data = $this->data($snapshot, $current);
        if ($current === null) {
            $id = (int)($this->processor->run('element/tv/create', $data)['id'] ?? 0);
            if (!$id) {
                throw new \RuntimeException("element/tv/create {$snapshot['fields']['name']}: не вернул id");
            }
            $this->ids->set('modTemplateVar', $old, $id);
        } else {
            $id = (int)$current['id'];
            if (!Canon::equal(self::body($current), self::body($snapshot))) {
                $this->processor->run('element/tv/update', ['id' => $id] + $data);
            }
        }
        $this->ranks($id, $snapshot['templates']);
        $this->values($id, (array)$snapshot['values']);
    }

    public function remove($id): void
    {
        if ($this->tvs->find((int)$id)) {
            $this->processor->run('element/tv/remove', ['id' => (int)$id]);
        }
    }

    /**
     * Данные element/tv/create|update из снимка: поля, inopt_*, prop_*, templates [{id, access, rank}].
     * $current — снять привязки, которых в снимке нет.
     */
    public function data(array $target, ?array $current): array
    {
        $data = (array)$target['fields'];
        foreach ((array)($target['input_properties'] ?? []) as $k => $v) {
            $data['inopt_' . $k] = $v;
        }
        foreach ((array)($target['output_properties'] ?? []) as $k => $v) {
            $data['prop_' . $k] = $v;
        }
        $templates = [];
        foreach ((array)($current['templates'] ?? []) as $link) {
            $templates[$link['template']] = ['id' => $link['template'], 'access' => false];
        }
        foreach ((array)($target['templates'] ?? []) as $link) {
            $templates[$link['template']] = ['id' => $link['template'], 'access' => true, 'rank' => $link['rank']];
        }
        $data['templates'] = json_encode(array_values($templates));
        $data['clearCache'] = 1;
        return $data;
    }

    /** element/tv/update rank привязки не пишет (только create) — ранги ставятся объектом modTemplateVarTemplate */
    private function ranks(int $id, array $templates): void
    {
        foreach ($templates as $link) {
            $obj = $this->modx->getObject('modTemplateVarTemplate', ['tmplvarid' => $id, 'templateid' => (int)$link['template']]);
            if ($obj && (int)$obj->get('rank') !== (int)$link['rank']) {
                $obj->set('rank', (int)$link['rank']);
                $obj->save();
            }
        }
    }

    /**
     * Значения TV у ресурсов. Процессора на значение одного TV нет (resource/update перезаписал бы ресурс целиком
     * и для каждого ресурса), поэтому — объектами modTemplateVarResource; кэш сайта сбрасывается после.
     */
    private function values(int $id, array $values): void
    {
        $changed = false;
        foreach ($this->modx->getIterator('modTemplateVarResource', ['tmplvarid' => $id], false) as $row) {
            $rid = (string)$row->get('contentid');
            if (!array_key_exists($rid, $values)) {
                $row->remove();
                $changed = true;
            } elseif ((string)$row->get('value') !== $values[$rid]) {
                $row->set('value', $values[$rid]);
                $row->save();
                $changed = true;
            }
            unset($values[$rid]);
        }
        foreach ($values as $rid => $value) {
            $row = $this->modx->newObject('modTemplateVarResource');
            $row->fromArray(['tmplvarid' => $id, 'contentid' => (int)$rid, 'value' => $value]);
            $row->save();
            $changed = true;
        }
        if ($changed) {
            $this->modx->cacheManager->refresh();
        }
    }

    private static function body(array $snapshot): array
    {
        unset($snapshot['id'], $snapshot['values']);
        return $snapshot;
    }
}
