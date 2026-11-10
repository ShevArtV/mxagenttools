<?php

namespace MxAgentTools\Tool;

/**
 * Паспорт инструмента — константами наследника, параметры — методом args().
 * Параметры — декларация mxApi: она же allow-list входа, приведение типов и документация для агента.
 */
abstract class AbstractTool implements ToolInterface
{
    const NAME = '';
    const KIND = self::READ;
    const TITLE = '';
    const DESCRIPTION = '';
    const PATH = '';

    public function name(): string
    {
        return static::NAME;
    }

    public function kind(): string
    {
        return static::KIND;
    }

    public function describe(): array
    {
        return ['title' => static::TITLE, 'description' => static::DESCRIPTION, 'path' => static::PATH,
            'parameters' => $this->args()];
    }

    /** @return array список параметров mxApi */
    abstract protected function args(): array;

    /** Параметры из словаря имя => {type, description, example?}; $required — обязательные */
    protected static function obj(array $properties, array $required = []): array
    {
        $out = [];
        foreach ($properties as $name => $spec) {
            $out[] = ['name' => $name] + $spec + ['required' => in_array($name, $required, true)];
        }
        return $out;
    }

    protected static function int(string $description, $example = null): array
    {
        return self::spec('integer', $description, $example);
    }

    protected static function str(string $description, $example = null): array
    {
        return self::spec('string', $description, $example);
    }

    protected static function map(string $description, $example = null): array
    {
        return self::spec('object', $description, $example);
    }

    protected static function ids(string $description, $example = null): array
    {
        return self::spec('array', $description, $example);
    }

    protected static function flag(string $description): array
    {
        return self::spec('boolean', $description, null);
    }

    private static function spec(string $type, string $description, $example): array
    {
        $out = ['type' => $type, 'description' => $description];
        if ($example !== null) {
            $out['example'] = $example;
        }
        return $out;
    }
}
