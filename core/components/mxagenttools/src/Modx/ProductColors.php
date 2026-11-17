<?php

namespace MxAgentTools\Modx;

use MxAgentTools\Core\ProcessorInterface;
use MxAgentTools\Core\Refusal;

/**
 * Образец цвета товара — строки msocColor (modx_msop_colors) пакета msOptionsColor, key=color.
 * Запись — процессорами пакета mgr/color/create|update (как вкладка «Цвета» у товара), удаление — объектом модели.
 * Ловушка пакета: getObject('msocColor', [rid, key, value]) при отсутствии строки сама СОЗДАЁТ пустую (msocColor::load),
 * поэтому читаем только запросом (xPDOQuery), а create (его initialize делает такой getObject) при ошибке чистим.
 */
class ProductColors
{
    const KEY = 'color';
    const PACKAGE = 'msoptionscolor';
    /** Поля строки, как в снимке; значение по умолчанию — для новой строки (не даём load() заполнить чужим при fill_colors_with_create) */
    const DEFAULTS = ['name' => '', 'color' => '', 'color2' => '', 'pattern' => '', 'pattern2' => '', 'image' => '0', 'image2' => '0',
        'active' => '1', 'description' => ''];
    /** Что берётся у донора: картинка/hex образца; image и image2 — id файлов галереи донора, чужому товару они не подходят */
    const COPY = ['pattern', 'pattern2', 'color', 'color2'];

    /** @var \modX */
    private $modx;
    /** @var ProcessorInterface */
    private $processor;

    public function __construct(\modX $modx, ProcessorInterface $processor)
    {
        $this->modx = $modx;
        $this->processor = $processor;
    }

    /** Права как у вкладки «Цвета»: правка товара */
    public function guardWrite(): void
    {
        if (!$this->modx->hasPermission('msproduct_save')) {
            throw new Refusal('у пользователя агента нет права msproduct_save — образец цвета товара править нельзя');
        }
    }

    public function title(int $product): string
    {
        $p = $this->modx->getObject('msProduct', ['id' => $product], false);
        if (!$p) {
            throw new Refusal("{$product} — не товар miniShop2");
        }
        return (string)$p->get('pagetitle');
    }

    /** @return string[] значения опции color товара */
    public function options(int $product): array
    {
        $p = $this->modx->getObject('msProduct', ['id' => $product], false);
        $values = $p ? (array)$p->loadData()->get('options') : [];
        $out = [];
        foreach ((array)($values[self::KEY] ?? []) as $v) {
            if (trim((string)$v) !== '') {
                $out[] = trim((string)$v);
            }
        }
        return array_values(array_unique($out));
    }

    /** @return array[] строки образцов товара (value + поля), по value */
    public function rows(int $product): array
    {
        $q = $this->modx->newQuery('msocColor');
        $q->where(['rid' => $product, 'key' => self::KEY])->sortby('value');
        $out = [];
        foreach ($this->modx->getIterator('msocColor', $q) as $row) {
            $out[] = self::fields($row);
        }
        return $out;
    }

    public function row(int $product, string $value): ?array
    {
        $obj = $this->find($product, $value);
        return $obj ? self::fields($obj) : null;
    }

    /** Товары с тем же значением color и непустым pattern (пустой — как у дубля товара 254 — не донор), по id */
    public function donors(string $value, int $except, int $limit): array
    {
        $q = $this->modx->newQuery('msocColor');
        $q->where(['key' => self::KEY, 'value' => $value, 'rid:!=' => $except, 'pattern:IS NOT' => null])->sortby('rid');
        $out = [];
        foreach ($this->modx->getIterator('msocColor', $q) as $row) {
            $f = self::fields($row);
            if (trim($f['pattern']) === '' || count($out) >= $limit) {
                continue;
            }
            $p = $this->modx->getObject('msProduct', ['id' => (int)$row->get('rid')], false);
            if ($p) {
                $out[] = ['product' => (int)$row->get('rid'), 'title' => (string)$p->get('pagetitle'), 'pattern' => $f['pattern']];
            }
        }
        return $out;
    }

    /**
     * Строка есть — mgr/color/update, нет — mgr/color/create с полями по умолчанию. Процессор create при ошибке мог оставить
     * пустую строку (её создаёт load() до проверок) — она убирается, если до вызова строки не было.
     */
    public function save(int $product, string $value, array $fields): void
    {
        $existed = $this->row($product, $value) !== null;
        $data = ['rid' => $product, 'key' => self::KEY, 'value' => $value] + $fields;
        try {
            $this->processor->run($existed ? 'mgr/color/update' : 'mgr/color/create', $existed ? $data : $data + self::DEFAULTS, self::PACKAGE);
        } catch (\Throwable $e) {
            $left = $existed ? null : $this->row($product, $value);
            if ($left && !array_filter(array_diff_key($left, ['active' => 1, 'value' => 1]))) {
                $this->delete($product, $value);
            }
            throw $e;
        }
        foreach ($fields as $k => $v) {
            $now = $this->row($product, $value);
            if ($now === null || ($now[$k] ?? null) !== (string)$v) {
                throw new \RuntimeException("образец цвета {$product}/{$value}: поле {$k} не записалось");
            }
        }
    }

    public function delete(int $product, string $value): void
    {
        $obj = $this->find($product, $value);
        if ($obj) {
            $obj->remove();
        }
    }

    /** Только запросом: getObject с массивом создал бы пустую строку */
    private function find(int $product, string $value): ?\xPDOObject
    {
        $q = $this->modx->newQuery('msocColor');
        $q->where(['rid' => $product, 'key' => self::KEY, 'value' => $value]);
        $obj = $this->modx->getObject('msocColor', $q);
        return $obj ?: null;
    }

    private static function fields(\xPDOObject $row): array
    {
        $out = ['value' => (string)$row->get('value')];
        foreach (array_keys(self::DEFAULTS) as $k) {
            $out[$k] = (string)$row->get($k);
        }
        return $out;
    }
}
