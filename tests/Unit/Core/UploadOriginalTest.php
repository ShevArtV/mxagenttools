<?php

namespace MxAgentTools\Tests\Unit\Core;

use MxAgentTools\Core\Uploads;
use MxAgentTools\Image\Images;
use MxAgentTools\Image\ImageOptions;
use MxAgentTools\Tests\Fakes\MemoryUploadStorage;
use PHPUnit\Framework\TestCase;

class UploadOriginalTest extends TestCase
{
    const DEFAULTS = ['max_side' => 2000, 'quality' => 82, 'format' => 'keep', 'strip' => true];

    public function testOriginalIgnoresCompressionKeys(): void
    {
        foreach ([true, 'true', '1', 'on'] as $v) {
            $this->assertNull(ImageOptions::forUpload(['original' => $v, 'max_width' => 10, 'quality' => 5,
                'format' => 'webp', 'strip_metadata' => true], self::DEFAULTS));
        }
    }

    public function testWithoutOriginalOptionsApplied(): void
    {
        foreach ([[], ['original' => false], ['original' => 'false'], ['original' => '0'], ['original' => '']] as $extra) {
            $o = ImageOptions::forUpload($extra + ['max_width' => 1200, 'quality' => 70], self::DEFAULTS);
            $this->assertInstanceOf(ImageOptions::class, $o);
            $this->assertSame([1200, 2000, 70], [$o->maxWidth, $o->maxHeight, $o->quality]);
        }
    }

    /** JPEG с EXIF Orientation 6: при original=true байты и sha256 не меняются, без original — перекодируется */
    public function testExifJpegUntouchedWhenOriginal(): void
    {
        $jpeg = base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA=');
        // APP1 Exif, TIFF big-endian, один IFD0-тег Orientation (0x0112) = 6
        $tiff = "MM\x00\x2a\x00\x00\x00\x08" . "\x00\x01" . "\x01\x12\x00\x03\x00\x00\x00\x01\x00\x06\x00\x00" . "\x00\x00\x00\x00";
        $exif = "Exif\x00\x00" . $tiff;
        $jpeg = substr($jpeg, 0, 2) . "\xff\xe1" . pack('n', strlen($exif) + 2) . $exif . substr($jpeg, 2);
        $sha = hash('sha256', $jpeg);
        $tmp = sys_get_temp_dir() . '/mxat-test-' . bin2hex(random_bytes(4));
        try {
            $uploads = new Uploads(new MemoryUploadStorage(), 3600, 1 << 20, $tmp, new Images());
            $orig = $uploads->put(base64_encode($jpeg), 'Photo.JPG', $sha,
                ImageOptions::forUpload(['original' => true], self::DEFAULTS));
            $this->assertSame([strlen($jpeg), $sha, 'Photo.JPG'], [$orig['size'], $orig['sha256'], $orig['name']]);
            $this->assertArrayNotHasKey('image', $orig);
            $this->assertSame($jpeg, file_get_contents($uploads->path($orig['token'])));
            $uploads->cleanTemp($orig['token']);

            if (!function_exists('imagecreatefromstring')) {
                return;
            }
            $packed = $uploads->put(base64_encode($jpeg), 'Photo.JPG', $sha, ImageOptions::forUpload([], self::DEFAULTS));
            $this->assertArrayHasKey('image', $packed, 'без original — прежнее поведение: обработка');
        } finally {
            Uploads::removeDir($tmp);
        }
    }
}
