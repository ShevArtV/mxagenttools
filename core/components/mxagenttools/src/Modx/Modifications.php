<?php

namespace MxAgentTools\Modx;

use MxAgentTools\Core\ProcessorInterface;
use MxAgentTools\Core\Refusal;
use MxAgentTools\Core\Values;
use RuntimeException;

/**
 * Модификации msOptionsPrice2 процессорами пакета, как кнопками админки, без SQL:
 * option/create (опция с mid=0, значение попадает и в опции товара ms2) → modification/create (забирает ВСЕ
 * опции товара с mid=0) → misc/gallery/update (фото). Поля — modification/update, удаление — modification/remove
 * (опции и фото удаляются каскадом модели).
 */
class Modifications
{
    /** Поля, которые пишет не modification/update: image процессор берёт из первого фото, rid — товар */
    const NOT_UPDATED = ['id', 'rid', 'image'];
    const P = 'msoptionsprice';

    /** @var \modX */
    private $modx;
    /** @var ProcessorInterface */
    private $processor;

    public function __construct(\modX $modx, ProcessorInterface $processor)
    {
        $this->modx = $modx;
        $this->processor = $processor;
    }

    public function snapshot(int $mid): ?array
    {
        $mod = $mid > 0 ? $this->modx->getObject('msopModification', $mid, false) : null;
        if (!$mod) {
            return null;
        }
        $fields = [];
        foreach (array_keys($this->modx->getFields('msopModification')) as $k) {
            if ($k !== 'id' && $k !== 'rid') {
                $fields[$k] = Values::str($mod->get($k));
            }
        }
        return ['id' => $mid, 'rid' => (int)$mod->get('rid'), 'fields' => $fields, 'options' => $this->options($mid),
            'images' => $this->images($mid)];
    }

    /** Поля msopModification без id и rid */
    public function fieldNames(): array
    {
        return array_values(array_diff(array_keys($this->modx->getFields('msopModification')), ['id', 'rid']));
    }

    /** @return int[] id модификаций товара по rank */
    public function ofProduct(int $rid): array
    {
        $q = $this->modx->newQuery('msopModification', ['rid' => $rid]);
        $q->sortby('rank');
        $q->sortby('id');
        $q->select('id');
        $out = [];
        if ($q->prepare() && $q->stmt->execute()) {
            $out = array_map('intval', $q->stmt->fetchAll(\PDO::FETCH_COLUMN));
        }
        return $out;
    }

    /** key => отсортированный список значений */
    public function options(int $mid): array
    {
        $out = [];
        foreach ($this->modx->getIterator('msopModificationOption', ['mid' => $mid], false) as $o) {
            $out[(string)$o->get('key')][] = (string)$o->get('value');
        }
        ksort($out, SORT_STRING);
        foreach ($out as $k => $v) {
            sort($v, SORT_STRING);
            $out[$k] = $v;
        }
        return $out;
    }

    /** [[image, rank], …] по rank */
    public function images(int $mid): array
    {
        $q = $this->modx->newQuery('msopModificationImage', ['mid' => $mid]);
        $q->sortby('rank');
        $q->sortby('image');
        $out = [];
        foreach ($this->modx->getIterator('msopModificationImage', $q, false) as $img) {
            $out[] = [(int)$img->get('image'), (int)$img->get('rank')];
        }
        return $out;
    }

    /** Опции товара с mid=0 (брошенная форма в админке или упавший прогон): их заберёт следующий modification/create */
    public function pending(int $rid): array
    {
        $out = [];
        foreach ($this->modx->getIterator('msopModificationOption', ['mid' => 0, 'rid' => $rid], false) as $o) {
            $out[] = ['key' => (string)$o->get('key'), 'value' => (string)$o->get('value')];
        }
        return $out;
    }

    public function assertNoPending(int $rid): void
    {
        if ($pending = $this->pending($rid)) {
            throw new Refusal('у товара есть недосохранённые опции модификации (mid=0) — modification/create забрал бы их '
                . 'в новую модификацию; уберите их в админке', ['pending_options' => $pending]);
        }
    }

    /** Новая модификация; ответ — id */
    public function create(int $rid, array $fields, array $options, array $images): int
    {
        $this->assertNoPending($rid);
        foreach ($options as $key => $values) {
            foreach ((array)$values as $value) {
                $this->processor->run('mgr/option/create', ['rid' => $rid, 'mid' => 0, 'key' => $key, 'value' => $value], self::P);
            }
        }
        $data = array_diff_key($fields, array_flip(self::NOT_UPDATED)) + ['rid' => $rid];
        $mid = (int)($this->processor->run('mgr/modification/create', $data, self::P)['id'] ?? 0);
        if (!$mid) {
            throw new RuntimeException('modification/create не вернул id');
        }
        $this->setImages($mid, $images);
        return $mid;
    }

    public function update(int $mid, array $fields): void
    {
        $this->processor->run('mgr/modification/update', ['id' => $mid] + array_diff_key($fields, array_flip(self::NOT_UPDATED)), self::P);
    }

    /**
     * Опции модификации: изменённый ключ снимается целиком (option/remove без field_name опции товара не трогает)
     * и пишется заново option/create с mid модификации — значение добавится и в опции товара ms2.
     */
    public function setOptions(int $mid, int $rid, array $target): void
    {
        $current = $this->options($mid);
        foreach (array_unique(array_merge(array_keys($current), array_keys($target))) as $key) {
            $want = Values::strList($target[$key] ?? []);
            sort($want, SORT_STRING);
            if (($current[$key] ?? []) === $want) {
                continue;
            }
            if (isset($current[$key])) {
                // процессор читает getProperty('id', $ids[0]): ids передаётся тоже, иначе notice на пустом ids
                $id = [$mid, $rid, $key, ''];
                $this->processor->run('mgr/option/remove', ['id' => $id, 'ids' => json_encode([$id])], self::P);
            }
            foreach ($want as $value) {
                $this->processor->run('mgr/option/create', ['rid' => $rid, 'mid' => $mid, 'key' => $key, 'value' => $value], self::P);
            }
        }
    }

    /**
     * Фото модификации целиком: снять все, добавить по порядку — ранги 0..n-1. misc/gallery/sort не используется:
     * его setRanks() пишет rank по image без mid и задевает чужие модификации; remove ранги не сдвигает —
     * поэтому всегда пересборка.
     */
    public function setImages(int $mid, array $images): void
    {
        foreach ($this->images($mid) as $pair) {
            $this->processor->run('mgr/misc/gallery/update', ['mid' => $mid, 'image' => $pair[0], 'mode' => 'remove'], self::P);
        }
        foreach ($images as $image) {
            $this->processor->run('mgr/misc/gallery/update', ['mid' => $mid, 'image' => (int)$image, 'mode' => 'add'], self::P);
        }
        // процессор обновляет image модификации только при смене первого фото; пустой список — поле не должно
        // указывать на снятое фото. Процессора на поле image нет — объектом.
        $this->setImageField($mid, (int)($images[0] ?? 0));
    }

    /**
     * Точные ранги и image из снимка: пересборка нумерует с 0, а ранги бывают не с 0 и image — не всегда первое
     * фото (тогда шаблоны, выбирающие фото rank 0, показали бы другое). Процессора на произвольный rank нет —
     * ранги пишутся объектами xPDO, как ms2 пишет rank фото галереи.
     */
    public function exactRanks(int $mid, array $pairs, int $image): void
    {
        foreach ($pairs as $pair) {
            $obj = $this->modx->getObject('msopModificationImage', ['mid' => $mid, 'image' => (int)$pair[0]]);
            if ($obj && (int)$obj->get('rank') !== (int)$pair[1]) {
                $obj->set('rank', (int)$pair[1]);
                $obj->save();
            }
        }
        $this->setImageField($mid, $image);
    }

    public function remove(int $mid): void
    {
        $this->processor->run('mgr/modification/remove', ['id' => $mid], self::P);
    }

    /** @return int[] модификации товара, у которых есть фото $image */
    public function withImage(int $rid, int $image): array
    {
        $out = [];
        foreach ($this->ofProduct($rid) as $mid) {
            if (in_array($image, array_column($this->images($mid), 0), true)) {
                $out[] = $mid;
            }
        }
        return $out;
    }

    private function setImageField(int $mid, int $image): void
    {
        $mod = $this->modx->getObject('msopModification', $mid);
        if ($mod && (int)$mod->get('image') !== $image) {
            $mod->set('image', $image);
            $mod->save();
        }
    }
}
