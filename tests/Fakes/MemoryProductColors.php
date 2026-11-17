<?php

namespace MxAgentTools\Tests\Fakes;

use MxAgentTools\Core\ProcessorInterface;
use MxAgentTools\Core\Refusal;
use MxAgentTools\Modx\ProductColors;

/**
 * Образцы цвета в памяти: rows[«товар:значение»] = поля. Товары 1–99 существуют, options — значения опции color.
 * Процессор create повторяет ловушку msocColor::load: пустая строка появляется ДО проверок.
 */
class MemoryProductColors extends ProductColors implements ProcessorInterface
{
    /** @var array[] */
    public $store = [];
    /** @var array[] product => значения опции color */
    public $opts = [];
    /** @var string[] ошибка процессора create для значения: value => сообщение */
    public $createFails = [];
    /** @var string[] вызовы процессора */
    public $calls = [];

    public function __construct()
    {
        $this->processorSelf();
    }

    private function processorSelf(): void
    {
        $ref = new \ReflectionProperty(ProductColors::class, 'processor');
        $ref->setAccessible(true);
        $ref->setValue($this, $this);
    }

    public function put(int $product, string $value, string $pattern, array $more = []): void
    {
        $this->store["{$product}:{$value}"] = ['value' => $value, 'pattern' => $pattern] + $more + self::DEFAULTS;
    }

    public function guardWrite(): void
    {
    }

    public function title(int $product): string
    {
        if ($product > 9999) {
            throw new Refusal("{$product} — не товар miniShop2");
        }
        return "Товар {$product}";
    }

    public function options(int $product): array
    {
        return $this->opts[$product] ?? [];
    }

    public function rows(int $product): array
    {
        $out = [];
        foreach ($this->store as $key => $row) {
            if (strpos($key, "{$product}:") === 0) {
                $out[] = $row;
            }
        }
        return $out;
    }

    public function row(int $product, string $value): ?array
    {
        return $this->store["{$product}:{$value}"] ?? null;
    }

    public function donors(string $value, int $except, int $limit): array
    {
        $out = [];
        foreach ($this->store as $key => $row) {
            $rid = (int)$key;
            if ($row['value'] === $value && $rid !== $except && trim($row['pattern']) !== '' && count($out) < $limit) {
                $out[] = ['product' => $rid, 'title' => "Товар {$rid}", 'pattern' => $row['pattern']];
            }
        }
        return $out;
    }

    public function delete(int $product, string $value): void
    {
        unset($this->store["{$product}:{$value}"]);
    }

    public function run(string $action, array $data, string $package = ''): array
    {
        $this->calls[] = $action;
        $key = "{$data['rid']}:{$data['value']}";
        if ($action === 'mgr/color/update') {
            $this->store[$key] = array_replace($this->store[$key], array_intersect_key($data, self::DEFAULTS));
            return [];
        }
        $this->store[$key] = $this->store[$key] ?? ['value' => $data['value']] + self::DEFAULTS; // load() создала пустую
        if (isset($this->createFails[$data['value']])) {
            throw new \RuntimeException($this->createFails[$data['value']]);
        }
        $this->store[$key] = array_replace($this->store[$key], array_intersect_key($data, self::DEFAULTS));
        return [];
    }

    public function resource(string $action, array $data, string $classKey): array
    {
        return [];
    }
}
