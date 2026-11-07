<?php

declare(strict_types=1);

namespace Youvision\ThinkImager;

use Intervention\Image\Alignment;
use Intervention\Image\FileExtension;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\DriverInterface;
use Intervention\Image\Interfaces\EncodedImageInterface;
use Intervention\Image\Interfaces\FontInterface;
use Intervention\Image\Interfaces\ImageInterface;
use SplFileInfo;
use Stringable;
use Throwable;
use Youvision\ThinkImager\exception\ImagerException;

/**
 * 图片处理入口
 *
 * 每次 read()/create() 会设置当前图片，后续方法通过链式调用操作同一张图片。
 * 通过 Facade 或 imager() 获取的实例均为全新实例，避免请求之间状态串用。
 */
class Imager
{
    /**
     * 驱动别名与完整类名映射
     */
    protected const DRIVERS = [
        'gd' => \Intervention\Image\Drivers\Gd\Driver::class,
        'imagick' => \Intervention\Image\Drivers\Imagick\Driver::class,
    ];

    /**
     * 各输出格式支持的编码器参数，避免向不支持该参数的编码器传参
     */
    protected const ENCODER_OPTIONS = [
        'jpg' => ['quality', 'strip', 'progressive'],
        'jpeg' => ['quality', 'strip', 'progressive'],
        'webp' => ['quality', 'strip'],
        'avif' => ['quality', 'strip'],
        'heic' => ['quality', 'strip'],
        'jxl' => ['quality', 'strip'],
        'tif' => ['quality', 'strip'],
        'tiff' => ['quality', 'strip'],
        'jp2' => ['quality', 'strip'],
        'png' => ['strip', 'interlaced', 'indexed'],
        'gif' => ['interlaced'],
        'bmp' => [],
        'ico' => [],
    ];

    protected static ?array $defaults = null;

    protected ImageManager $manager;

    protected array $config;

    protected ?ImageInterface $image = null;

    protected ?string $source_path = null;

    public function __construct(array $config = [])
    {
        $this->config = self::mergeDefaults($config);
        $this->manager = $this->createManager();
    }

    /**
     * 读取图片。
     *
     * 支持：文件路径、SplFileInfo（含 TP 上传文件）、二进制内容、Data URI、
     * ImageInterface 实例、资源流。不存在文件路径的字符串将按二进制内容解析。
     */
    public function read(mixed $source): static
    {
        if ($source instanceof ImageInterface) {
            $this->image = $source;
            $this->source_path = $source->origin()->filePath();

            return $this;
        }

        if ($source instanceof SplFileInfo) {
            return $this->readPath($source->getPathname());
        }

        if (is_resource($source)) {
            return $this->readStream($source);
        }

        if ($source instanceof Stringable) {
            $source = (string) $source;
        }

        if (is_string($source)) {
            if (is_file($source)) {
                return $this->readPath($source);
            }

            if (str_starts_with($source, 'data:image/')) {
                return $this->readDataUri($source);
            }

            return $this->readBinary($source);
        }

        try {
            $this->image = $this->manager->decode($source);
        } catch (Throwable $e) {
            throw new ImagerException('读取图片失败：' . $e->getMessage(), 0, $e);
        }

        $this->source_path = $this->image->origin()->filePath();

        return $this;
    }

    /**
     * 读取本地图片文件
     */
    public function readPath(string|Stringable $path): static
    {
        $path = (string) $path;

        if (!is_file($path)) {
            throw new ImagerException('图片文件不存在：' . $path);
        }

        if ((bool) $this->config['check_ext']) {
            $this->assertAllowedExtension($path);
        }

        try {
            $this->image = $this->manager->decodePath($path);
        } catch (Throwable $e) {
            throw new ImagerException('读取图片失败：' . $path . '，' . $e->getMessage(), 0, $e);
        }

        $this->source_path = $path;

        return $this;
    }

    /**
     * 读取二进制图片内容
     */
    public function readBinary(string|Stringable $binary): static
    {
        try {
            $this->image = $this->manager->decodeBinary((string) $binary);
        } catch (Throwable $e) {
            throw new ImagerException('读取图片二进制内容失败：' . $e->getMessage(), 0, $e);
        }

        $this->source_path = null;

        return $this;
    }

    /**
     * 读取 Base64 图片内容
     */
    public function readBase64(string|Stringable $base64): static
    {
        try {
            $this->image = $this->manager->decodeBase64((string) $base64);
        } catch (Throwable $e) {
            throw new ImagerException('读取 Base64 图片失败：' . $e->getMessage(), 0, $e);
        }

        $this->source_path = null;

        return $this;
    }

    /**
     * 读取 Data URI 图片内容
     */
    public function readDataUri(string|Stringable $dataUri): static
    {
        try {
            $this->image = $this->manager->decodeDataUri((string) $dataUri);
        } catch (Throwable $e) {
            throw new ImagerException('读取 Data URI 图片失败：' . $e->getMessage(), 0, $e);
        }

        $this->source_path = null;

        return $this;
    }

    /**
     * 读取图片资源流
     */
    public function readStream(mixed $stream): static
    {
        try {
            $this->image = $this->manager->decodeStream($stream);
        } catch (Throwable $e) {
            throw new ImagerException('读取图片资源流失败：' . $e->getMessage(), 0, $e);
        }

        $this->source_path = null;

        return $this;
    }

    /**
     * read() 的别名，兼容 think-image 的 make() 用法
     */
    public function make(mixed $source): static
    {
        return $this->read($source);
    }

    /**
     * 创建指定宽高的空白图片
     */
    public function create(int $width, int $height): static
    {
        if ($width < 1 || $height < 1) {
            throw new ImagerException('图片宽度和高度必须大于 0');
        }

        try {
            $this->image = $this->manager->createImage($width, $height);
        } catch (Throwable $e) {
            throw new ImagerException('创建图片失败：' . $e->getMessage(), 0, $e);
        }

        $this->source_path = null;

        return $this;
    }

    /**
     * 生成缩略图并保存
     *
     * 未指定的参数会读取 imager.thumb 配置；保存路径为空时会根据
     * imager.thumb_path 配置或原图目录生成“原文件名_thumb.扩展名”。
     */
    public function thumb(
        ?string $path = null,
        ?int $width = null,
        ?int $height = null,
        ?string $mode = null,
        ?int $quality = null
    ): static {
        $config = (array) $this->config['thumb'];
        $width = $width ?? (int) ($config['width'] ?? 0);
        $height = $height ?? (int) ($config['height'] ?? 0);
        $mode = $mode ?? (string) ($config['mode'] ?? 'cover');
        $quality = $quality ?? (int) ($config['quality'] ?? $this->config['quality']);

        if ($width < 1 || $height < 1) {
            throw new ImagerException('缩略图宽度和高度必须大于 0');
        }

        $this->applyThumb($mode, $width, $height);

        return $this->save($path ?? $this->makeThumbPath(), $quality);
    }

    /**
     * 添加图片水印
     *
     * 未指定的参数会读取 imager.watermark 配置。
     */
    public function watermark(
        ?string $file = null,
        ?string $position = null,
        ?int $x = null,
        ?int $y = null,
        ?int $opacity = null
    ): static {
        $config = (array) $this->config['watermark'];
        $file = $file ?? (string) ($config['file'] ?? '');

        if ($file === '') {
            throw new ImagerException('未配置水印图片，请设置 imager.watermark.file 或调用时传入水印路径');
        }

        if (!is_file($file)) {
            throw new ImagerException('水印图片不存在：' . $file);
        }

        $position = $position ?? (string) ($config['position'] ?? 'bottom-right');
        $x = $x ?? (int) ($config['offset_x'] ?? 0);
        $y = $y ?? (int) ($config['offset_y'] ?? 0);
        $opacity = max(0, min(100, $opacity ?? (int) ($config['opacity'] ?? 100)));

        try {
            $alignment = Alignment::create($position);
        } catch (Throwable $e) {
            throw new ImagerException('不支持的水印位置：' . $position, 0, $e);
        }

        try {
            $mark = $this->manager->decodePath($file);
        } catch (Throwable $e) {
            throw new ImagerException('读取水印图片失败：' . $file . '，' . $e->getMessage(), 0, $e);
        }

        $this->image()->insert($mark, $x, $y, $alignment, $opacity / 100);

        return $this;
    }

    /**
     * 添加文字
     */
    public function text(string $text, int $x, int $y, callable|FontInterface $font): static
    {
        $this->image()->text($text, $x, $y, $font);

        return $this;
    }

    /**
     * 保存图片
     *
     * 路径为空时覆盖当前图片的来源文件；路径目录不存在时会自动创建。
     * quality 为空时读取 imager.quality，strip 为空时使用驱动配置中的 strip。
     * 额外编码参数可通过 $options 传入，例如 ['progressive' => true]。
     */
    public function save(
        ?string $path = null,
        ?int $quality = null,
        ?bool $strip = null,
        array $options = []
    ): static {
        $image = $this->image();
        $target = $path ?? $this->source_path ?? $image->origin()->filePath();

        if ($target === null || $target === '') {
            throw new ImagerException('保存路径不能为空，请显式传入文件路径');
        }

        $extension = strtolower((string) pathinfo($target, PATHINFO_EXTENSION));

        if ($extension === '') {
            throw new ImagerException('保存路径必须包含图片扩展名：' . $target);
        }

        $this->ensureDirectory($target);

        $quality = $quality ?? (int) $this->config['quality'];
        $encoder_options = $this->encoderOptions($extension, $quality, $strip, $options);

        try {
            $image->save($target, ...$encoder_options);
        } catch (Throwable $e) {
            throw new ImagerException('保存图片失败：' . $target . '，' . $e->getMessage(), 0, $e);
        }

        $this->source_path = $target;

        return $this;
    }

    /**
     * 编码图片，返回编码结果而不写入文件
     *
     * 扩展名为空时读取 imager.format 配置。
     */
    public function encode(
        ?string $extension = null,
        ?int $quality = null,
        ?bool $strip = null,
        array $options = []
    ): EncodedImageInterface {
        $extension = strtolower($extension ?? (string) $this->config['format']);
        $extension = $extension !== '' ? $extension : 'jpg';
        $quality = $quality ?? (int) $this->config['quality'];
        $encoder_options = $this->encoderOptions($extension, $quality, $strip, $options);

        try {
            return $this->image()->encodeUsingFileExtension($extension, ...$encoder_options);
        } catch (Throwable $e) {
            throw new ImagerException('编码图片失败：' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * 输出 Data URI
     */
    public function dataUri(?string $extension = null, ?int $quality = null): string
    {
        return $this->encode($extension, $quality)->toDataUri()->toString();
    }

    /**
     * 输出 Base64 字符串
     */
    public function base64(?string $extension = null, ?int $quality = null): string
    {
        return $this->encode($extension, $quality)->toBase64();
    }

    /**
     * 获取当前图片宽度
     */
    public function width(): int
    {
        return $this->image()->width();
    }

    /**
     * 获取当前图片高度
     */
    public function height(): int
    {
        return $this->image()->height();
    }

    /**
     * 获取当前图片 MIME 类型
     */
    public function mime(): string
    {
        $mediaType = $this->image()->origin()->mediaType();

        if ($mediaType !== 'application/octet-stream') {
            return $mediaType;
        }

        $extension = $this->extension();

        if ($extension !== null && $extension !== '') {
            try {
                return FileExtension::from($extension)->mediaType()->value;
            } catch (Throwable) {
                // 未知扩展名时保留原始 MIME
            }
        }

        return $mediaType;
    }

    /**
     * 获取当前图片扩展名
     */
    public function extension(): ?string
    {
        $extension = $this->image()->origin()->fileExtension();

        if ($extension !== null && $extension !== '') {
            return strtolower($extension);
        }

        if ($this->source_path !== null) {
            $extension = strtolower((string) pathinfo($this->source_path, PATHINFO_EXTENSION));

            if ($extension !== '') {
                return $extension;
            }
        }

        return null;
    }

    /**
     * 获取当前图片对象
     */
    public function image(): ImageInterface
    {
        if (!$this->image instanceof ImageInterface) {
            throw new ImagerException('尚未读取或创建图片，请先调用 read()/readPath()/create()');
        }

        return $this->image;
    }

    /**
     * 获取 Intervention Image 管理器
     */
    public function manager(): ImageManager
    {
        return $this->manager;
    }

    /**
     * 获取当前图片驱动
     */
    public function driver(): DriverInterface
    {
        return $this->manager->driver;
    }

    /**
     * 获取配置，支持点语法：config('thumb.width')
     */
    public function config(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->config;
        }

        $value = $this->config;

        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * 获取当前图片来源路径（二进制、Base64 等来源返回 null）
     */
    public function sourcePath(): ?string
    {
        return $this->source_path;
    }

    /**
     * 清空当前图片状态，便于在同一实例上重新开始
     */
    public function reset(): static
    {
        $this->image = null;
        $this->source_path = null;

        return $this;
    }

    /**
     * 读取不存在的路径时按二进制解析；其余未知方法转发给当前图片对象。
     */
    public function __call(string $method, array $arguments): mixed
    {
        $result = $this->image()->{$method}(...$arguments);

        return $result instanceof ImageInterface ? $this : $result;
    }

    /**
     * 合并包默认配置与用户配置
     */
    public static function mergeDefaults(array $config): array
    {
        return self::merge(static::defaultConfig(), $config);
    }

    /**
     * 获取包默认配置
     */
    public static function defaultConfig(): array
    {
        return self::$defaults ??= require dirname(__DIR__) . '/config/imager.php';
    }

    /**
     * 递归合并配置；用户传入的列表（如 allowed_ext）整体覆盖默认值。
     */
    protected static function merge(array $default, array $config): array
    {
        foreach ($config as $key => $value) {
            if (is_array($value) && isset($default[$key]) && is_array($default[$key])) {
                if ($value !== [] && array_is_list($value)) {
                    $default[$key] = $value;
                } else {
                    $default[$key] = self::merge($default[$key], $value);
                }
            } else {
                $default[$key] = $value;
            }
        }

        return $default;
    }

    /**
     * 创建 Intervention Image 管理器
     */
    protected function createManager(): ImageManager
    {
        $driver = $this->config['driver'] ?? 'gd';

        if (is_string($driver)) {
            $driver = self::DRIVERS[strtolower($driver)] ?? $driver;
        }

        if ($driver === self::DRIVERS['imagick'] && !extension_loaded('imagick')) {
            throw new ImagerException('Imagick 扩展未安装，无法使用 imagick 驱动');
        }

        $options = (array) ($this->config['options'] ?? []);
        $options['autoOrientation'] = (bool) $this->config['auto_orient'];
        $options['strip'] = (bool) $this->config['strip'];

        try {
            return new ImageManager($driver, ...$options);
        } catch (Throwable $e) {
            throw new ImagerException('图片驱动初始化失败：' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * 按扩展名校验读取文件的扩展名白名单
     */
    protected function assertAllowedExtension(string $path): void
    {
        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));

        if ($extension === '') {
            return;
        }

        $allowed = array_map(
            static fn (mixed $item): string => strtolower((string) $item),
            (array) $this->config['allowed_ext']
        );

        if (!in_array($extension, $allowed, true)) {
            throw new ImagerException('不允许读取的图片扩展名：' . $extension);
        }
    }

    /**
     * 执行缩略图缩放
     */
    protected function applyThumb(string $mode, int $width, int $height): void
    {
        $image = $this->image();
        $mode = strtolower(str_replace(['-', '_'], '', $mode));

        match ($mode) {
            'resize' => $image->resize($width, $height),
            'resizedown' => $image->resizeDown($width, $height),
            'scale' => $image->scale($width, $height),
            'scaledown' => $image->scaleDown($width, $height),
            'cover' => $image->cover($width, $height),
            'coverdown' => $image->coverDown($width, $height),
            'contain' => $image->contain($width, $height),
            'containdown' => $image->containDown($width, $height),
            'crop' => $image->crop($width, $height),
            default => throw new ImagerException('不支持的缩略图模式：' . $mode),
        };
    }

    /**
     * 根据缩略图配置生成默认保存路径
     */
    protected function makeThumbPath(): string
    {
        if ($this->source_path === null || $this->source_path === '') {
            throw new ImagerException('二进制图片生成缩略图时必须显式指定保存路径');
        }

        $directory = (string) ($this->config['thumb_path'] ?? '');

        if ($directory === '') {
            $directory = dirname($this->source_path);
        }

        $extension = strtolower((string) pathinfo($this->source_path, PATHINFO_EXTENSION));
        $extension = $extension !== '' ? $extension : 'jpg';
        $filename = (string) pathinfo($this->source_path, PATHINFO_FILENAME);

        return rtrim($directory, '/\\') . DIRECTORY_SEPARATOR . $filename . '_thumb.' . $extension;
    }

    /**
     * 构建编码器参数，过滤掉目标格式不支持的参数
     */
    protected function encoderOptions(
        string $extension,
        ?int $quality,
        ?bool $strip,
        array $options
    ): array {
        $allowed = self::ENCODER_OPTIONS[$extension] ?? null;

        if ($allowed === null) {
            return $options;
        }

        $result = array_intersect_key($options, array_flip($allowed));

        if ($quality !== null && in_array('quality', $allowed, true)) {
            $result['quality'] = max(0, min(100, $quality));
        }

        if ($strip !== null && in_array('strip', $allowed, true)) {
            $result['strip'] = $strip;
        }

        return $result;
    }

    /**
     * 确保保存目录存在
     */
    protected function ensureDirectory(string $path): void
    {
        $directory = dirname($path);

        if ($directory === '' || $directory === '.' || is_dir($directory)) {
            return;
        }

        if (!mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new ImagerException('无法创建保存目录：' . $directory);
        }
    }
}