<?php

namespace MxAgentTools\Tests\Unit\Core;

use MxAgentTools\Core\Refusal;
use MxAgentTools\Image\ImageOptions;
use MxAgentTools\Image\Images;
use PHPUnit\Framework\TestCase;

class ImageOptionsTest extends TestCase
{
    public function testDefaultsAndOverrides(): void
    {
        $d = ['max_side' => 2000, 'quality' => 82, 'format' => 'keep', 'strip' => true];
        $o = ImageOptions::from([], $d);
        $this->assertSame([2000, 2000, 82, 'keep', true, false], [$o->maxWidth, $o->maxHeight, $o->quality, $o->format, $o->strip,
            $o->explicitQuality]);
        $o = ImageOptions::from(['max_width' => 1200, 'quality' => 70, 'format' => 'webp', 'strip_metadata' => false], $d);
        $this->assertSame([1200, 2000, 70, 'webp', false, true], [$o->maxWidth, $o->maxHeight, $o->quality, $o->format, $o->strip,
            $o->explicitQuality]);
        $this->expectException(Refusal::class);
        ImageOptions::from(['format' => 'bmp'], $d);
    }

    public function testFitAndExt(): void
    {
        $o = new ImageOptions(2000, 2000, 82, 'keep', true);
        $this->assertSame([2000, 1500], $o->fit(4000, 3000));
        $this->assertSame([1500, 2000], $o->fit(3000, 4000));
        $this->assertSame([800, 600], $o->fit(800, 600));
        $this->assertSame([4000, 3000], (new ImageOptions(0, 0, 82, 'keep', true))->fit(4000, 3000));
        $this->assertSame('jpg', $o->ext('jpeg'));
        $this->assertSame('webp', (new ImageOptions(0, 0, 82, 'webp', true))->ext('png'));
    }

    public function testNonImagesUntouched(): void
    {
        $images = new Images();
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"/>';
        [$data, $name, $report] = $images->prepare($svg, 'logo.svg', new ImageOptions(10, 10, 50, 'jpeg', true));
        $this->assertSame([$svg, 'logo.svg', false], [$data, $name, $report['processed']]);
        [$data] = $images->prepare('%PDF-1.4', 'a.pdf', new ImageOptions(10, 10, 50, 'jpeg', true));
        $this->assertSame('%PDF-1.4', $data);
    }

    /** Движок есть (на серверах — Imagick или GD): 3000×2000 JPEG вписывается в 1000 и худеет */
    public function testResizeWithEngine(): void
    {
        $engine = Images::detect();
        if ($engine === null) {
            $this->markTestSkipped('нет Imagick и GD');
        }
        if (class_exists('Imagick')) {
            $im = new \Imagick();
            $im->newPseudoImage(3000, 2000, 'plasma:');
            $im->setImageFormat('jpeg');
            $im->setImageCompressionQuality(98);
            $src = $im->getImageBlob();
        } else {
            $img = imagecreatetruecolor(3000, 2000);
            for ($i = 0; $i < 3000; $i += 7) {
                imageline($img, $i, 0, 3000 - $i, 2000, imagecolorallocate($img, $i % 255, (2 * $i) % 255, (3 * $i) % 255));
            }
            ob_start();
            imagejpeg($img, null, 98);
            $src = ob_get_clean();
        }
        [$data, $name, $report] = (new Images())->prepare($src, 'big.jpeg', new ImageOptions(1000, 1000, 80, 'webp', true));
        $this->assertTrue($report['processed']);
        $this->assertSame('big.webp', $name);
        $this->assertSame([1000, 667], [$report['after']['width'], $report['after']['height']]);
        $this->assertLessThan(strlen($src), strlen($data));
    }
}
