<?php

namespace MxAgentTools\Tests\Unit\Core;

use MxAgentTools\Core\Refusal;
use MxAgentTools\Core\SafePath;
use PHPUnit\Framework\TestCase;

class SafePathTest extends TestCase
{
    /** @var string */
    private $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/mxat-safe-' . bin2hex(random_bytes(4));
        mkdir($this->dir . '/elements/chunks', 0700, true);
        file_put_contents($this->dir . '/elements/chunks/a.tpl', 'A');
        file_put_contents($this->dir . '/secret.php', 'S');
        symlink($this->dir . '/secret.php', $this->dir . '/elements/chunks/link.tpl');
        symlink($this->dir, $this->dir . '/elements/up');
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->dir));
    }

    public function testInsideAllowed(): void
    {
        $root = SafePath::root($this->dir . '/elements/');
        $real = SafePath::resolve($root, 'chunks/a.tpl');
        $this->assertSame('chunks/a.tpl', SafePath::relative($root, $real));
        $this->assertSame('chunks/a.tpl', SafePath::relative($root, SafePath::resolve($root, '/chunks/./a.tpl')));
        $this->assertSame('chunks/', SafePath::relative($root, SafePath::resolve($root, 'chunks', true)));
    }

    /** @dataProvider escapes */
    public function testEscapesRefused(string $path): void
    {
        $root = SafePath::root($this->dir . '/elements/');
        $this->expectException(Refusal::class);
        SafePath::resolve($root, $path);
    }

    public function escapes(): array
    {
        return [['../secret.php'], ['chunks/../../secret.php'], ['chunks/link.tpl'], ['up/secret.php'], ['chunks/none.tpl'],
            ["chunks/a.tpl\0.x"], ['chunks']];
    }
}
