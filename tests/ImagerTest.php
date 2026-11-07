<?php

declare(strict_types=1);

namespace Youvision\ThinkImager\Tests;

use PHPUnit\Framework\TestCase;
use Youvision\ThinkImager\exception\ImagerException;
use Youvision\ThinkImager\Imager;

final class ImagerTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'think-imager-' . uniqid('', true);

        if (!mkdir($this->directory, 0755, true) && !is_dir($this->directory)) {
            $this->fail('无法创建测试目录');
        }
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->directory);
    }

    public function testDriverConfigIsApplied(): void
    {
        $imager = new Imager([
            'driver' => 'gd',
            'auto_orient' => false,
            'strip' => false,
            'options' => ['decodeAnimation' => false],
        ]);

        $this->assertSame('GD', $imager->driver()->id());
        $this->assertFalse($imager->driver()->config()->autoOrientation);
        $this->assertFalse($imager->driver()->config()->strip);
        $this->assertFalse($imager->driver()->config()->decodeAnimation);
    }

    public function testCreateAndSave(): void
    {
        $path = $this->directory . '/create.png';

        $imager = new Imager(['driver' => 'gd']);
        $imager->create(320, 200)->fill('#336699')->save($path);

        $this->assertFileExists($path);
        $this->assertSame([320, 200], $this->imageSize($path));
        $this->assertSame(320, $imager->width());
        $this->assertSame(200, $imager->height());
        $this->assertSame('image/png', $imager->mime());
        $this->assertSame('png', $imager->extension());
        $this->assertSame($path, $imager->sourcePath());
    }

    public function testReadResizeAndSave(): void
    {
        $source = $this->directory . '/source.jpg';
        $target = $this->directory . '/resized.jpg';

        (new Imager())->create(800, 600)->fill('#ff0000')->save($source);

        (new Imager())->read($source)->scale(200)->save($target);

        $this->assertFileExists($target);
        $this->assertSame([200, 150], $this->imageSize($target));
    }

    public function testQualityConfigAffectsOutputSize(): void
    {
        $source = $this->directory . '/noise.jpg';

        $this->createNoiseImage($source, 500, 500);

        $low = $this->directory . '/low.jpg';
        $high = $this->directory . '/high.jpg';

        (new Imager(['quality' => 10]))->read($source)->save($low);
        (new Imager(['quality' => 95]))->read($source)->save($high);

        $this->assertFileExists($low);
        $this->assertFileExists($high);
        $this->assertGreaterThan(filesize($low), filesize($high));
    }

    public function testThumbUsesConfigAndThumbPath(): void
    {
        $source = $this->directory . '/photo.png';
        (new Imager())->create(900, 600)->fill('#00ff00')->save($source);

        $thumbDirectory = $this->directory . '/thumbs';

        $imager = new Imager([
            'thumb_path' => $thumbDirectory,
            'thumb' => [
                'width' => 120,
                'height' => 80,
                'mode' => 'cover',
                'quality' => 80,
            ],
        ]);

        $imager->read($source)->thumb();

        $target = $thumbDirectory . '/photo_thumb.png';

        $this->assertFileExists($target);
        $this->assertSame([120, 80], $this->imageSize($target));
    }

    public function testThumbSupportsAllModes(): void
    {
        $source = $this->directory . '/mode.png';
        (new Imager())->create(400, 300)->fill('#123456')->save($source);

        foreach (['resize', 'scale', 'cover', 'contain', 'crop', 'resize_down', 'scale_down', 'cover_down', 'contain_down'] as $mode) {
            $target = $this->directory . '/mode_' . $mode . '.png';
            (new Imager())->read($source)->thumb($target, 100, 100, $mode);
            $this->assertFileExists($target, '缩略图模式失败：' . $mode);
        }
    }

    public function testWatermarkConfigIsApplied(): void
    {
        $base = $this->directory . '/base.png';
        $watermark = $this->directory . '/watermark.png';
        $target = $this->directory . '/watermarked.png';

        (new Imager())->create(200, 200)->fill('#ffffff')->save($base);
        (new Imager())->create(40, 40)->fill('#ff0000')->save($watermark);

        $imager = new Imager([
            'watermark' => [
                'file' => $watermark,
                'position' => 'top-left',
                'offset_x' => 0,
                'offset_y' => 0,
                'opacity' => 100,
            ],
        ]);

        $imager->read($base)->watermark()->save($target);

        $this->assertFileExists($target);

        $pixel = $this->pixelAt($target, 20, 20);
        $this->assertGreaterThan(200, $pixel['red']);
        $this->assertLessThan(60, $pixel['green']);
        $this->assertLessThan(60, $pixel['blue']);
    }

    public function testWatermarkRequiresFile(): void
    {
        $this->expectException(ImagerException::class);
        $this->expectExceptionMessage('未配置水印图片');

        (new Imager())->create(100, 100)->watermark();
    }

    public function testCheckExtRejectsDisallowedExtension(): void
    {
        $file = $this->directory . '/fake.txt';
        file_put_contents($file, 'not an image');

        $imager = new Imager([
            'check_ext' => true,
            'allowed_ext' => ['png', 'jpg'],
        ]);

        $this->expectException(ImagerException::class);
        $this->expectExceptionMessage('不允许读取的图片扩展名');

        $imager->readPath($file);
    }

    public function testCheckExtAcceptsAllowedExtension(): void
    {
        $file = $this->directory . '/allowed.png';
        (new Imager())->create(10, 10)->fill('#ffffff')->save($file);

        $imager = new Imager([
            'check_ext' => true,
            'allowed_ext' => ['png'],
        ]);

        $this->assertSame('png', $imager->readPath($file)->extension());
    }

    public function testUnsupportedThumbModeThrows(): void
    {
        $this->expectException(ImagerException::class);
        $this->expectExceptionMessage('不支持的缩略图模式');

        (new Imager())->create(100, 100)->thumb($this->directory . '/bad.png', 50, 50, 'unknown');
    }

    public function testThumbBinaryRequiresPath(): void
    {
        $binary = (string) (new Imager())->create(30, 30)->fill('#ffffff')->encode('png');

        $this->expectException(ImagerException::class);
        $this->expectExceptionMessage('必须显式指定保存路径');

        (new Imager())->readBinary($binary)->thumb();
    }

    public function testEncodeDefaultFormatConfig(): void
    {
        $encoded = (new Imager(['format' => 'png']))->create(20, 20)->fill('#000000')->encode();

        $this->assertSame('image/png', $encoded->mediaType());
    }

    public function testDataUriAndBase64(): void
    {
        $imager = (new Imager(['format' => 'jpg']))->create(16, 16)->fill('#abcdef');

        $this->assertStringStartsWith('data:image/jpeg;base64,', $imager->dataUri());
        $this->assertNotSame('', $imager->base64());
    }

    public function testReadBase64AndDataUri(): void
    {
        $source = (new Imager())->create(32, 24)->fill('#445566');
        $base64 = $source->base64('png');
        $dataUri = $source->dataUri('png');

        $fromBase64 = new Imager();
        $fromBase64->readBase64($base64);
        $this->assertSame([32, 24], [$fromBase64->width(), $fromBase64->height()]);

        $fromDataUri = new Imager();
        $fromDataUri->readDataUri($dataUri);
        $this->assertSame([32, 24], [$fromDataUri->width(), $fromDataUri->height()]);
    }

    public function testSaveRequiresPathForBinarySource(): void
    {
        $binary = (string) (new Imager())->create(10, 10)->encode('png');

        $this->expectException(ImagerException::class);
        $this->expectExceptionMessage('保存路径不能为空');

        (new Imager())->readBinary($binary)->save();
    }

    public function testSaveCreatesDirectory(): void
    {
        $target = $this->directory . '/a/b/c/out.png';

        (new Imager())->create(10, 10)->fill('#000000')->save($target);

        $this->assertFileExists($target);
    }

    public function testSaveWithoutExtensionThrows(): void
    {
        $this->expectException(ImagerException::class);
        $this->expectExceptionMessage('保存路径必须包含图片扩展名');

        (new Imager())->create(10, 10)->save($this->directory . '/no-extension');
    }

    public function testImageRequired(): void
    {
        $this->expectException(ImagerException::class);
        $this->expectExceptionMessage('尚未读取或创建图片');

        (new Imager())->width();
    }

    public function testReset(): void
    {
        $imager = (new Imager())->create(10, 10);

        $this->assertSame(10, $imager->width());
        $imager->reset();

        $this->expectException(ImagerException::class);
        $imager->width();
    }

    public function testConfigSupportsDotNotationAndDefaults(): void
    {
        $imager = new Imager(['thumb' => ['width' => 128]]);

        $this->assertSame(128, $imager->config('thumb.width'));
        $this->assertSame('cover', $imager->config('thumb.mode'));
        $this->assertSame('missing', $imager->config('thumb.missing', 'missing'));
    }

    public function testReadSplFileInfo(): void
    {
        $source = $this->directory . '/spl.png';
        (new Imager())->create(40, 30)->fill('#000000')->save($source);

        $imager = (new Imager())->read(new \SplFileInfo($source));

        $this->assertSame(40, $imager->width());
        $this->assertSame(30, $imager->height());
    }

    /**
     * @return array{0:int,1:int}
     */
    private function imageSize(string $path): array
    {
        $info = getimagesize($path);
        $this->assertIsArray($info);

        return [$info[0], $info[1]];
    }

    /**
     * @return array{red:int,green:int,blue:int}
     */
    private function pixelAt(string $path, int $x, int $y): array
    {
        $image = imagecreatefrompng($path);
        $this->assertNotFalse($image);

        $color = imagecolorat($image, $x, $y);
        $this->assertIsInt($color);

        return [
            'red' => ($color >> 16) & 0xFF,
            'green' => ($color >> 8) & 0xFF,
            'blue' => $color & 0xFF,
        ];
    }

    private function createNoiseImage(string $path, int $width, int $height): void
    {
        $image = imagecreatetruecolor($width, $height);
        $this->assertNotFalse($image);

        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                $color = imagecolorallocate(
                    $image,
                    random_int(0, 255),
                    random_int(0, 255),
                    random_int(0, 255)
                );
                imagesetpixel($image, $x, $y, $color);
            }
        }

        imagejpeg($image, $path, 100);
        imagedestroy($image);
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = scandir($directory);

        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $item;

            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($directory);
    }
}