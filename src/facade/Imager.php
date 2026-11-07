<?php

declare(strict_types=1);

namespace Youvision\ThinkImager\facade;

use think\Facade;
use Youvision\ThinkImager\Imager as ImagerClass;

/**
 * @see \Youvision\ThinkImager\Imager
 * @method static \Youvision\ThinkImager\Imager read(mixed $source)
 * @method static \Youvision\ThinkImager\Imager readPath(string|\Stringable $path)
 * @method static \Youvision\ThinkImager\Imager readBinary(string|\Stringable $binary)
 * @method static \Youvision\ThinkImager\Imager readBase64(string|\Stringable $base64)
 * @method static \Youvision\ThinkImager\Imager readDataUri(string|\Stringable $dataUri)
 * @method static \Youvision\ThinkImager\Imager create(int $width, int $height)
 * @method static \Youvision\ThinkImager\Imager make(mixed $source)
 * @method static \Youvision\ThinkImager\Imager resize(?int $width = null, ?int $height = null)
 * @method static \Youvision\ThinkImager\Imager scale(?int $width = null, ?int $height = null)
 * @method static \Youvision\ThinkImager\Imager cover(int $width, int $height, string|\Intervention\Image\Alignment $alignment = \Intervention\Image\Alignment::CENTER)
 * @method static \Youvision\ThinkImager\Imager contain(int $width, int $height, null|string|\Intervention\Image\Interfaces\ColorInterface $background = null)
 * @method static \Youvision\ThinkImager\Imager crop(int $width, int $height, int $x = 0, int $y = 0)
 * @method static \Youvision\ThinkImager\Imager rotate(float $angle, null|string|\Intervention\Image\Interfaces\ColorInterface $background = null)
 * @method static \Youvision\ThinkImager\Imager blur(int $level = 5)
 * @method static \Youvision\ThinkImager\Imager brightness(int $level)
 * @method static \Youvision\ThinkImager\Imager contrast(int $level)
 * @method static \Youvision\ThinkImager\Imager grayscale()
 * @method static \Youvision\ThinkImager\Imager sharpen(int $level = 10)
 * @method static \Youvision\ThinkImager\Imager orient()
 * @method static \Youvision\ThinkImager\Imager fill(string|\Intervention\Image\Interfaces\ColorInterface $color, ?int $x = null, ?int $y = null)
 * @method static \Youvision\ThinkImager\Imager text(string $text, int $x, int $y, callable|\Intervention\Image\Interfaces\FontInterface $font)
 * @method static \Youvision\ThinkImager\Imager insert(mixed $image, int $x = 0, int $y = 0, string|\Intervention\Image\Alignment $alignment = \Intervention\Image\Alignment::TOP_LEFT, float $transparency = 1)
 * @method static \Youvision\ThinkImager\Imager thumb(?string $path = null, ?int $width = null, ?int $height = null, ?string $mode = null, ?int $quality = null)
 * @method static \Youvision\ThinkImager\Imager watermark(?string $file = null, ?string $position = null, ?int $x = null, ?int $y = null, ?int $opacity = null)
 * @method static \Youvision\ThinkImager\Imager save(?string $path = null, ?int $quality = null, ?bool $strip = null, array $options = [])
 * @method static int width()
 * @method static int height()
 * @method static string mime()
 * @method static string|null extension()
 * @method static string dataUri(?string $extension = null, ?int $quality = null)
 * @method static string base64(?string $extension = null, ?int $quality = null)
 * @method static mixed config(?string $key = null, mixed $default = null)
 * @method static \Intervention\Image\Interfaces\EncodedImageInterface encode(?string $extension = null, ?int $quality = null, ?bool $strip = null, array $options = [])
 * @method static \Intervention\Image\Interfaces\ImageInterface image()
 * @method static \Intervention\Image\ImageManager manager()
 * @method static \Intervention\Image\Interfaces\DriverInterface driver()
 * @method static \Youvision\ThinkImager\Imager reset()
 */
class Imager extends Facade
{
    /**
     * 每次静态调用都创建新的 Imager 实例，避免链式状态在请求之间串用。
     *
     * @var bool
     */
    protected static $alwaysNewInstance = true;

    protected static function getFacadeClass(): string
    {
        return ImagerClass::class;
    }
}