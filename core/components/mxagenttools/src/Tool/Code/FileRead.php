<?php

namespace MxAgentTools\Tool\Code;

use MxAgentTools\Core\SafePath;
use MxAgentTools\Core\Values;
use MxAgentTools\Modx\CodeFiles;
use MxAgentTools\Tool\AbstractTool;
use MxAgentTools\Tool\ReadToolInterface;

class FileRead extends AbstractTool implements ReadToolInterface
{
    const NAME = 'file.read';
    const TITLE = 'Файловый элемент: содержимое';
    const PATH = '/agent/files/read';
    const DESCRIPTION = 'Содержимое файла из каталога файловых элементов (pdotools_elements_path): path — от корня, как в '
        . 'file.list. Выход за каталог, симлинки наружу и файлы больше mxagenttools.file_max_kb — отказ. Нужно, чтобы понять, '
        . 'где и как выводится поле, прежде чем его править. Только чтение.';

    /** @var CodeFiles */
    private $files;

    public function __construct(CodeFiles $files)
    {
        $this->files = $files;
    }

    protected function args(): array
    {
        return self::obj(['path' => self::str('путь от корня каталога элементов', 'chunks/catalog/banner.tpl')], ['path']);
    }

    public function read(array $args): array
    {
        $root = $this->files->root();
        $real = SafePath::resolve($root, Values::text($args, 'path', true));
        return ['path' => SafePath::relative($root, $real), 'size' => (int)filesize($real), 'content' => $this->files->read($real)];
    }
}
