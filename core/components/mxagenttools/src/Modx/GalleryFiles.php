<?php

namespace MxAgentTools\Modx;

use MxAgentTools\Core\ProcessorInterface;
use MxAgentTools\Core\Refusal;
use RuntimeException;

/**
 * Галерея товара ms2 процессорами, как кнопками админки: mgr/gallery/upload (превью и updateProductImage
 * штатно), mgr/gallery/remove (оригинал и превью с диска), mgr/gallery/sort (порядок).
 */
class GalleryFiles
{
    const FIELDS = ['id', 'name', 'description', 'file', 'path', 'url', 'type', 'hash', 'rank'];

    /** @var \modX */
    private $modx;
    /** @var ProcessorInterface */
    private $processor;

    public function __construct(\modX $modx, ProcessorInterface $processor)
    {
        $this->modx = $modx;
        $this->processor = $processor;
    }

    public function product(int $rid): \msProduct
    {
        $product = $rid > 0 ? $this->modx->getObject('msProduct', $rid) : null;
        if (!$product) {
            throw new Refusal("товара {$rid} нет (ресурс не msProduct)");
        }
        return $product;
    }

    /** Оригиналы галереи (без превью) по порядку: id => поля */
    public function files(int $rid): array
    {
        $q = $this->modx->newQuery('msProductFile', ['product_id' => $rid, 'parent' => 0]);
        $q->sortby('`rank`'); // RANK зарезервирован в MySQL 8
        $q->sortby('id');
        $out = [];
        foreach ($this->modx->getIterator('msProductFile', $q, false) as $f) {
            $row = [];
            foreach (self::FIELDS as $k) {
                $row[$k] = $k === 'id' || $k === 'rank' ? (int)$f->get($k) : (string)$f->get($k);
            }
            $out[$row['id']] = $row;
        }
        return $out;
    }

    public function file(int $id): \msProductFile
    {
        $file = $id > 0 ? $this->modx->getObject('msProductFile', ['id' => $id, 'parent' => 0]) : null;
        if (!$file) {
            throw new Refusal("фото {$id} нет в галерее");
        }
        return $file;
    }

    /** Загрузить файл штатным upload; id новой записи */
    public function upload(int $rid, string $path, string $description): int
    {
        $object = $this->processor->run('mgr/gallery/upload', ['id' => $rid, 'file' => $path, 'description' => $description], 'minishop2');
        $id = (int)($object['id'] ?? 0);
        if (!$id) {
            throw new RuntimeException('gallery/upload не вернул id фото');
        }
        return $id;
    }

    public function remove(int $id): void
    {
        $this->processor->run('mgr/gallery/remove', ['id' => $id], 'minishop2');
    }

    /**
     * Порядок галереи процессором sort (source встаёт на rank target, остальные сдвигаются): по одному фото на место.
     * Одинаковые rank (после remove + upload в конец) sort не различает — сначала сплошная нумерация объектами,
     * как сохраняет её ms2 (msProductFile::save переносит rank и превью); процессора на произвольный rank нет.
     */
    public function order(int $rid, array $ids): void
    {
        $ranks = $this->ranks($rid);
        if (count(array_unique($ranks)) !== count($ranks)) {
            $i = 0;
            foreach (array_keys($ranks) as $id) {
                $file = $this->file($id);
                $file->set('rank', $i++);
                $file->save();
            }
        }
        foreach (array_values($ids) as $i => $id) {
            $ranks = $this->ranks($rid);
            $current = array_keys($ranks);
            if (!isset($current[$i]) || $current[$i] === (int)$id || !isset($ranks[(int)$id])) {
                continue;
            }
            $this->processor->run('mgr/gallery/sort', ['product_id' => $rid, 'source' => (int)$id, 'target' => $current[$i]], 'minishop2');
        }
    }

    /** Сброс кэша сайта после записи галереи — как сохранение товара в админке */
    public function refresh(): void
    {
        $this->modx->cacheManager->refresh();
    }

    /** Байты оригинала через медиаисточник товара */
    public function content(int $id): string
    {
        $file = $this->file($id);
        $file->prepareSource();
        $got = $file->mediaSource->getObjectContents($file->get('path') . $file->get('file'));
        if (!is_array($got) || !isset($got['content']) || $got['content'] === '') {
            throw new RuntimeException("оригинал фото {$id} не читается — удалять не буду (откат был бы невозможен)");
        }
        return $got['content'];
    }

    /** Что принимает галерея товара: расширения медиаисточника и какие из них картинки */
    public function accepts(int $rid): array
    {
        $source = $this->product($rid)->initializeMediaSource();
        if (!$source) {
            throw new RuntimeException("у товара {$rid} нет медиаисточника галереи");
        }
        $props = $source->getPropertyList();
        $split = function ($v) {
            return array_values(array_filter(array_map('trim', explode(',', strtolower((string)$v)))));
        };
        return ['source' => (int)$source->get('id'), 'extensions' => $split($props['allowedFileTypes'] ?? ''),
            'images' => $split($props['imageExtensions'] ?? '')];
    }

    /** id => rank оригиналов по порядку (без кэша объектов: sort пишет rank SQL-запросом) */
    private function ranks(int $rid): array
    {
        $out = [];
        foreach ($this->files($rid) as $id => $f) {
            $out[$id] = $f['rank'];
        }
        return $out;
    }
}
