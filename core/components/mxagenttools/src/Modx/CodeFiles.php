<?php

namespace MxAgentTools\Modx;

use MxAgentTools\Core\Refusal;
use MxAgentTools\Core\SafePath;

/**
 * Чтение кода сайта: каталог файловых элементов pdoTools/Fenom (настройка pdotools_elements_path, подстановки
 * {core_path}, {base_path}, {assets_path}) и файлы статичных элементов — только внутри корня сайта или core.
 * Размер — до mxagenttools.file_max_kb.
 */
class CodeFiles
{
    /** @var \modX */
    private $modx;
    /** @var int */
    private $maxBytes;

    public function __construct(\modX $modx, int $maxKb)
    {
        $this->modx = $modx;
        $this->maxBytes = max(1, $maxKb) * 1024;
    }

    public function root(): string
    {
        $path = (string)$this->modx->getOption('pdotools_elements_path', null, '{core_path}elements/');
        return SafePath::root($this->expand($path));
    }

    /** Содержимое файла; больше лимита — отказ с размером */
    public function read(string $real): string
    {
        $size = (int)filesize($real);
        if ($size > $this->maxBytes) {
            throw new Refusal('файл ' . round($size / 1024) . ' КиБ больше лимита ' . ($this->maxBytes / 1024)
                . ' КиБ (mxagenttools.file_max_kb)');
        }
        $data = file_get_contents($real);
        if ($data === false) {
            throw new Refusal('файл не читается');
        }
        return $data;
    }

    /** Файл статичного элемента: только внутри base_path или core_path; null — не статичный или вне границ */
    public function staticFile(\modElement $element): ?array
    {
        if (!$element->isStatic()) {
            return null;
        }
        $file = (string)$element->getSourceFile();
        $real = $file !== '' ? realpath($file) : false;
        if ($real === false || !is_file($real)) {
            return ['file' => $file, 'error' => 'файла нет'];
        }
        foreach ([MODX_BASE_PATH, MODX_CORE_PATH] as $allowed) {
            $root = realpath($allowed);
            if ($root !== false && strpos($real, rtrim($root, '/') . '/') === 0) {
                try {
                    return ['file' => $real, 'content' => $this->read($real)];
                } catch (Refusal $e) {
                    return ['file' => $real, 'error' => $e->getMessage()];
                }
            }
        }
        return ['file' => $file, 'error' => 'файл вне каталога сайта — не читается'];
    }

    private function expand(string $path): string
    {
        return str_replace(['{core_path}', '{base_path}', '{assets_path}', '{assets_url}'],
            [(string)$this->modx->getOption('core_path', null, MODX_CORE_PATH), (string)$this->modx->getOption('base_path', null, MODX_BASE_PATH),
                (string)$this->modx->getOption('assets_path'), (string)$this->modx->getOption('assets_path')], $path);
    }
}
