<?php

namespace MxAgentTools\Entity;

use MxAgentTools\Core\Canon;
use MxAgentTools\Core\ProcessorInterface;
use MxAgentTools\Core\Values;
use MxAgentTools\Modx\ProductData;
use MxAgentTools\Modx\TemplateVars;
use RuntimeException;

/**
 * Ресурс: поля, все TV шаблона (сырые значения), у товара ms2 — данные товара, опции, доп. категории.
 * Удаляется мягко — в корзину MODX (resource/delete), отмена — resource/undelete. Внимание: undelete MODX 2.8
 * возвращает и всех удалённых потомков, в том числе удалённых раньше отдельно.
 */
class Resource implements EntityInterface, SoftDeleteInterface
{
    /** Меняются при каждом сохранении или пишутся процессором сами — в снимок не входят */
    const VOLATILE = ['id', 'editedon', 'editedby', 'createdon', 'createdby', 'publishedon', 'publishedby',
        'deletedon', 'deletedby', 'properties'];

    /** @var \modX */
    private $modx;
    /** @var ProcessorInterface */
    private $processor;
    /** @var TemplateVars */
    private $tvs;
    /** @var ProductData|null */
    private $product;
    /** @var ResourcePayload */
    private $payload;

    public function __construct(\modX $modx, ProcessorInterface $processor, TemplateVars $tvs, ?ProductData $product)
    {
        $this->modx = $modx;
        $this->processor = $processor;
        $this->tvs = $tvs;
        $this->product = $product;
        $this->payload = new ResourcePayload($tvs, $product);
    }

    public function type(): string
    {
        return 'resource';
    }

    public function snapshot($id): ?array
    {
        /** @var \modResource|null $res */
        $res = $this->modx->getObject('modResource', (int)$id, false);
        if (!$res) {
            return null;
        }
        $fields = [];
        foreach (array_keys($this->modx->getFields('modResource')) as $k) {
            if (!in_array($k, self::VOLATILE, true)) {
                $fields[$k] = Values::str($res->get($k));
            }
        }
        $out = ['id' => (int)$id, 'class_key' => $fields['class_key'], 'fields' => $fields,
            'tvs' => $this->tvs->values((int)$fields['template'], (int)$id)];
        if ($this->product && $fields['class_key'] === 'msProduct') {
            $out += $this->product->snapshot((int)$id);
        }
        return $out;
    }

    public function isRemoved(array $snapshot): bool
    {
        return ($snapshot['fields']['deleted'] ?? '0') === '1';
    }

    /** Привести ресурс к снимку: из корзины, поля и TV полным набором через resource/update, в корзину */
    public function restore(array $snapshot): void
    {
        $id = (int)$snapshot['id'];
        $current = $this->snapshot($id);
        if ($current === null) {
            throw new RuntimeException("ресурса {$id} нет в базе (корзину очистили) — вернуть его нельзя");
        }
        if ($this->isRemoved($current) && !$this->isRemoved($snapshot)) {
            $this->processor->run('resource/undelete', ['id' => $id]);
            $current = (array)$this->snapshot($id);
        }
        if (!Canon::equal(self::body($current), self::body($snapshot))) {
            $this->write($snapshot, $current);
        }
        if (!$this->isRemoved($current) && $this->isRemoved($snapshot)) {
            $this->processor->run('resource/delete', ['id' => $id]);
        }
    }

    public function remove($id): void
    {
        $current = $this->snapshot($id);
        if ($current !== null && !$this->isRemoved($current)) {
            $this->processor->run('resource/delete', ['id' => (int)$id]);
        }
    }

    /** Запись снимка процессором по class_key; ответ — имя процессора */
    public function write(array $target, array $current): string
    {
        $data = $this->payload->build($target, $current);
        return $this->processor->resource('resource/update', $data, (string)$data['class_key'])['processor'];
    }

    public function payload(): ResourcePayload
    {
        return $this->payload;
    }

    /** Снимок без корзины и вычисляемого uri — то, что пишет resource/update */
    private static function body(array $snapshot): array
    {
        unset($snapshot['fields']['deleted']);
        if (($snapshot['fields']['uri_override'] ?? '0') !== '1') {
            unset($snapshot['fields']['uri']);
        }
        return $snapshot;
    }
}
