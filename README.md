# youvision/think-imager

基于 [Intervention Image](https://github.com/Intervention/image) v4 的 ThinkPHP 8 图片处理扩展。

- 支持 `gd`、`imagick` 驱动
- 链式调用：读取、缩放、裁剪、旋转、水印、编码、保存
- 支持本地文件、TP 上传文件、二进制、Base64、Data URI、资源流
- 内置缩略图与水印配置
- 配置项全部使用中文注释，默认可直接运行

## 环境要求

| 依赖 | 版本 |
| --- | --- |
| PHP | >= 8.3 |
| ThinkPHP | ^8.0 |
| intervention/image | ^4.0 |
| PHP 扩展 | gd 或 imagick（默认 gd），建议安装 exif、fileinfo |

> Intervention Image v4 的 API 与 v2/v3 不兼容，本包只支持 v4。

## 安装

```bash
composer require youvision/think-imager
```

安装完成后，ThinkPHP 8 骨架通常会在 `post-autoload-dump` 中自动执行服务发现与配置发布。如果未自动生效，可手动执行：

```bash
php think service:discover
php think vendor:publish
```

`vendor:publish` 会将配置文件复制到项目 `config/imager.php`。该步骤是可选的：即使不发布，服务也会自动合并包内默认配置。

## 配置

配置文件：`config/imager.php`

| 配置项 | 类型 | 默认值 | 说明 |
| --- | --- | --- | --- |
| `driver` | string | `gd` | 图片驱动，支持 `gd`、`imagick` 或 DriverInterface 完整类名 |
| `options` | array | `[]` | 传给驱动 Config 的附加参数，如 `decodeAnimation`、`backgroundColor` |
| `quality` | int | `90` | 默认输出质量，0-100，仅对 JPEG/WebP/AVIF 等有损格式有效 |
| `strip` | bool | `true` | 保存时是否去除 EXIF/ICC 等元数据，写入驱动配置 `strip` |
| `format` | string | `jpg` | `encode()`、`dataUri()`、`base64()` 未指定扩展名时的默认格式 |
| `auto_orient` | bool | `true` | 是否按 EXIF Orientation 自动纠正方向，写入驱动 `autoOrientation` |
| `check_ext` | bool | `false` | 读取本地文件时是否校验扩展名 |
| `allowed_ext` | array | 常见图片扩展名 | 扩展名白名单，仅 `check_ext=true` 时生效 |
| `thumb_path` | string | 空 | 缩略图默认目录；为空时保存到原图目录 |
| `thumb.width` | int | `300` | 缩略图默认宽度 |
| `thumb.height` | int | `300` | 缩略图默认高度 |
| `thumb.mode` | string | `cover` | 缩略图模式：`resize`、`scale`、`cover`、`contain`、`crop` 及对应的 `_down` 模式 |
| `thumb.quality` | int | `90` | 缩略图输出质量 |
| `watermark.file` | string | 空 | 水印图片路径，为空时调用 `watermark()` 必须显式传入 |
| `watermark.position` | string | `bottom-right` | 水印位置，支持 `top-left`、`top`、`center`、`bottom-right` 等 9 个位置 |
| `watermark.offset_x` | int | `10` | 水印横向偏移（像素） |
| `watermark.offset_y` | int | `10` | 水印纵向偏移（像素） |
| `watermark.opacity` | int | `100` | 水印不透明度，0-100 |

所有配置项都会在运行代码中实际使用，可在测试 `tests/ImagerTest.php` 中查看对应验证。

## 快速使用

### Facade

```php
use Youvision\ThinkImager\facade\Imager;

// 读取图片并按宽度等比缩放
Imager::read('uploads/a.jpg')->scale(800)->save('uploads/a_800.jpg');

// 裁剪填满 300x300，质量 85
Imager::read('uploads/a.jpg')->cover(300, 300)->save('uploads/a_thumb.jpg', 85);

// 创建纯色图片
Imager::create(600, 400)->fill('#2d8cf0')->save('uploads/blue.png');
```

### 依赖注入 / 容器

```php
use Youvision\ThinkImager\Imager;

public function handle(Imager $imager)
{
    $imager->read($file)->resize(800, 600)->save($path);
}
```

### 全局助手函数

```php
imager()->read($file)->scale(600)->save($path);

// 传入自定义配置
imager(['quality' => 75])->read($file)->save($path);
```

> Facade 与 `imager()` 每次都会返回全新实例，链式状态不会在请求之间串用。

## 常用操作

### 读取来源

```php
// 本地文件
Imager::read('/path/to/a.jpg');

// ThinkPHP 上传文件（UploadedFile 继承 SplFileInfo）
Imager::read($request->file('image'));

// 二进制内容
Imager::readBinary($binary);

// Base64 / Data URI
Imager::readBase64($base64);
Imager::readDataUri('data:image/png;base64,...');

// 资源流
Imager::readStream(fopen('/path/to/a.jpg', 'r'));
```

`read()` 会自动判断：存在的文件路径按文件读取，`data:image/` 开头按 Data URI 读取，其余字符串按二进制读取。

### 缩放与裁剪

```php
$imager = Imager::read($file);

$imager->resize(800, 600);   // 强制缩放到 800x600
$imager->scale(800);         // 按宽度等比缩放
$imager->cover(300, 300);    // 等比缩放并裁剪填满
$imager->contain(300, 300);  // 等比缩放并留白
$imager->crop(200, 200);     // 从左上角裁剪
$imager->rotate(45);         // 旋转 45 度
```

### 缩略图

```php
// 使用 config/imager.php 中的 thumb 配置
Imager::read($file)->thumb();

// 指定路径与尺寸
Imager::read($file)->thumb('uploads/thumb/a.jpg', 300, 300, 'cover', 85);
```

未传路径时，默认保存为 `原文件名_thumb.扩展名`，目录由 `thumb_path` 决定；`thumb_path` 为空则使用原图目录。

### 水印

```php
// 使用配置中的水印图片、位置与透明度
Imager::read($file)->watermark()->save($target);

// 显式指定水印图片、位置、偏移、透明度
Imager::read($file)
    ->watermark('watermark.png', 'bottom-right', 20, 20, 60)
    ->save($target);
```

### 编码与输出

```php
// 保存
Imager::read($file)->save('output.jpg', 90);

// 指定格式编码，不写入文件
$encoded = Imager::read($file)->encode('webp', 85);
file_put_contents('output.webp', (string) $encoded);

// Data URI / Base64
$dataUri = Imager::read($file)->dataUri('jpg', 80);
$base64  = Imager::read($file)->base64('png');
```

### 获取图片信息

```php
$imager = Imager::read($file);

$imager->width();     // 宽度
$imager->height();    // 高度
$imager->mime();      // MIME 类型
$imager->extension(); // 扩展名
$imager->sourcePath();// 来源路径
```

### 其他 Intervention Image 方法

未显式封装的方法会通过 `__call()` 转发给 Intervention Image 的 `Image` 对象，并保持链式调用：

```php
Imager::read($file)
    ->brightness(10)
    ->contrast(5)
    ->sharpen(8)
    ->save($target);
```

## 错误处理

统一抛出 `Youvision\ThinkImager\exception\ImagerException`：

```php
use Youvision\ThinkImager\exception\ImagerException;

try {
    Imager::read($file)->save($target);
} catch (ImagerException $e) {
    // 文件不存在、驱动未安装、保存失败等
}
```

## 开发与测试

```bash
composer install
vendor/bin/phpunit
```

## 许可证

MIT