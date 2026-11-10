<?php

namespace MxAgentTools\Tool\Code;

use MxAgentTools\Core\SafePath;
use MxAgentTools\Core\Values;
use MxAgentTools\Modx\CodeFiles;
use MxAgentTools\Tool\AbstractTool;
use MxAgentTools\Tool\ReadToolInterface;

class FileList extends AbstractTool implements ReadToolInterface
{
    const NAME = 'file.list';
    const TITLE = 'Файловые элементы: список';
    const PATH = '/agent/files';
    const MAX = 1000;
    const DESCRIPTION = 'Файлы каталога файловых элементов pdoTools/Fenom (настройка pdotools_elements_path): чанки, шаблоны, '
        . 'сниппеты, которые подключаются как file:… / @FILE. dir — подкаталог (пусто — корень), depth — глубина (по '
        . 'умолчанию 3). Ответ: root, files [{path, size, modified}] до 1000 штук, truncated. Только чтение: файлы — код '
        . 'сайта, их меняют разработчики. Содержимое — file.read.';

    /** @var CodeFiles */
    private $files;

    public function __construct(CodeFiles $files)
    {
        $this->files = $files;
    }

    protected function args(): array
    {
        return self::obj(['dir' => self::str('подкаталог от корня', 'chunks/catalog'), 'depth' => self::int('глубина 1–10', 3)]);
    }

    public function read(array $args): array
    {
        $root = $this->files->root();
        $dir = Values::text($args, 'dir') === '' ? $root : SafePath::resolve($root, Values::text($args, 'dir'), true);
        $depth = max(1, min(10, (int)($args['depth'] ?? 3)));
        $out = [];
        $truncated = false;
        $walk = function (string $path, int $level) use (&$walk, &$out, &$truncated, $root, $depth) {
            foreach (scandir($path) ?: [] as $name) {
                if ($name === '.' || $name === '..' || $truncated) {
                    continue;
                }
                $full = realpath($path . $name);
                if ($full === false || strpos($full . (is_dir($full) ? '/' : ''), $root) !== 0) {
                    continue; // симлинк наружу
                }
                if (is_dir($full)) {
                    if ($level < $depth) {
                        $walk($full . '/', $level + 1);
                    }
                    continue;
                }
                if (count($out) >= self::MAX) {
                    $truncated = true;
                    return;
                }
                $out[] = ['path' => SafePath::relative($root, $full), 'size' => (int)filesize($full),
                    'modified' => date('c', (int)filemtime($full))];
            }
        };
        $walk($dir, 1);
        return ['root' => $root, 'dir' => SafePath::relative($root, $dir), 'files' => $out, 'truncated' => $truncated];
    }
}
