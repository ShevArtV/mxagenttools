<?php

namespace MxAgentTools\Tests\Unit\Modx;

use MxAgentTools\Core\Uploads;
use MxAgentTools\Modx\UploadBlobs;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class UploadBlobsTest extends TestCase
{
    /** @var string */
    private $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/mxat-blobs-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        Uploads::removeDir($this->dir);
    }

    public function testRoundTripBinaryByteForByte(): void
    {
        $blobs = new UploadBlobs($this->dir . '/uploads');
        $token = bin2hex(random_bytes(16));
        $data = random_bytes(3 * 1048576);
        $blobs->put($token, $data);
        $this->assertSame(hash('sha256', $data), hash('sha256', (string)$blobs->get($token)));
        $blobs->drop($token);
        $this->assertNull($blobs->get($token));
    }

    public function testBadTokenRefused(): void
    {
        $this->expectException(RuntimeException::class);
        (new UploadBlobs($this->dir))->put('../etc/passwd', 'x');
    }
}
