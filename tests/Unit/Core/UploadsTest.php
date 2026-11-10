<?php

namespace MxAgentTools\Tests\Unit\Core;

use MxAgentTools\Core\Refusal;
use MxAgentTools\Core\Uploads;
use MxAgentTools\Tests\Fakes\MemoryUploadStorage;
use PHPUnit\Framework\TestCase;

class UploadsTest extends TestCase
{
    /** @var MemoryUploadStorage */
    private $storage;
    /** @var string */
    private $tmp;

    protected function setUp(): void
    {
        $this->storage = new MemoryUploadStorage();
        $this->tmp = sys_get_temp_dir() . '/mxat-test-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        Uploads::removeDir($this->tmp);
    }

    public function testPutAndPath(): void
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        $uploads = new Uploads($this->storage, 3600, 1024, $this->tmp);
        $got = $uploads->put(base64_encode($png), '../../Фото товара.PNG', hash('sha256', $png));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $got['token']);
        $this->assertSame('Фото_товара.PNG', $got['name']);
        $this->assertSame(['png', 1, 1, 'image/png'], [$got['ext'], $got['width'], $got['height'], $got['mime']]);
        $path = $uploads->path($got['token']);
        $this->assertSame($png, file_get_contents($path));
        $this->assertSame('Фото_товара.PNG', basename($path));
        $this->assertSame($this->tmp . '/' . $got['token'], dirname($path), 'временный файл — в каталоге, заданном сайтом');
        $uploads->cleanTemp($got['token']);
        $this->assertFileDoesNotExist($path);
        $this->assertSame('Фото_товара.PNG', $uploads->name($got['token']), 'cleanTemp оставляет файл в хранилище');
        $uploads->drop($got['token']);
        $this->expectException(Refusal::class);
        $uploads->name($got['token']);
    }

    /** @dataProvider badPuts */
    public function testRefusals(string $data, string $name, string $sha): void
    {
        $this->expectException(Refusal::class);
        (new Uploads($this->storage, 3600, 16, $this->tmp))->put($data, $name, $sha);
    }

    public function badPuts(): array
    {
        return [
            'не base64' => ['@@@', 'a.jpg', ''],
            'пусто' => ['', 'a.jpg', ''],
            'больше лимита' => [base64_encode(str_repeat('x', 17)), 'a.jpg', ''],
            'sha256 не совпал' => [base64_encode('abc'), 'a.jpg', str_repeat('0', 64)],
            'без имени' => [base64_encode('abc'), '...', ''],
        ];
    }

    public function testExpiredAndBadToken(): void
    {
        $uploads = new Uploads($this->storage, -10, 1024, $this->tmp);
        $this->storage->save(str_repeat('a', 32), 'a.jpg', '', 'x', time() - 1);
        try {
            $uploads->path(str_repeat('a', 32));
            $this->fail('просрочен');
        } catch (Refusal $e) {
            $this->addToAssertionCount(1);
        }
        $this->expectException(Refusal::class);
        $uploads->path('../etc/passwd');
    }
}
