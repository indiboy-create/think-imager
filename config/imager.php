<?php

declare(strict_types=1);

/*
 * think-imager 配置文件
 *
 * 安装后执行 `php think vendor:publish` 可将本文件发布到项目 config/imager.php，
 * 发布后只需保留需要修改的配置项，未修改的项会自动使用本文件的默认值。
 */

return [
    /*
     * 图片驱动
     * 支持：gd、imagick，也可以填写实现 Intervention\Image\Interfaces\DriverInterface 的完整类名。
     * gd：使用 PHP GD 扩展，兼容性最好，为默认值。
     * imagick：使用 ImageMagick 扩展，需要服务器已安装 imagick 扩展。
     */
    'driver' => 'gd',

    /*
     * 驱动附加参数
     * 原样传递给 Intervention Image 驱动的 Config，例如：
     * [
     *     'decodeAnimation' => true,       // 是否解码 GIF/WebP 动图
     *     'backgroundColor' => '#ffffff',  // 透明区域填充的背景色
     * ]
     * 注意：下面的 auto_orient、strip 会覆盖此处的 autoOrientation、strip。
     */
    'options' => [],

    /*
     * 默认输出质量
     * 取值范围 0-100，数值越大画质越好、文件越大。
     * 仅对 JPEG、WebP、AVIF、HEIC、JXL、TIFF、JP2 等有损格式有效；
     * PNG 为无损格式，该配置不会写入 PNG 编码器。
     */
    'quality' => 90,

    /*
     * 保存时是否去除图片元数据（EXIF、ICC 等）
     * true：去除元数据；false：保留元数据。
     * 该值会写入驱动配置 strip，编码时未显式指定 strip 则使用此值。
     */
    'strip' => true,

    /*
     * encode()、dataUri()、base64() 未指定扩展名时使用的默认格式。
     * 支持 jpg、jpeg、png、gif、webp、bmp、avif、heic、jxl、tif、tiff、jp2、ico。
     */
    'format' => 'jpg',

    /*
     * 读取图片时是否根据 EXIF Orientation 自动纠正方向
     * true：自动旋转到正确方向；false：保持原始像素方向。
     * 该值会写入驱动配置 autoOrientation，由解码器在读取阶段完成。
     */
    'auto_orient' => true,

    /*
     * 读取本地图片文件时是否校验扩展名
     * true：仅允许 allowed_ext 中的扩展名；false：不校验扩展名。
     */
    'check_ext' => false,

    /*
     * 允许读取的图片扩展名白名单，仅在 check_ext=true 时生效。
     */
    'allowed_ext' => [
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp',
        'avif', 'heic', 'jxl', 'tif', 'tiff', 'jp2', 'ico',
    ],

    /*
     * 缩略图默认保存目录
     * 空字符串：保存到原图所在目录。
     * 相对路径：相对于 PHP 进程的当前工作目录，建议使用绝对路径或 runtime_path()。
     */
    'thumb_path' => '',

    /*
     * 缩略图默认参数
     * width/height：目标宽度、高度（像素）。
     * mode：缩放模式，支持 resize、scale、cover、contain、crop，
     *       以及 resize_down、scale_down、cover_down、contain_down。
     * quality：缩略图输出质量（0-100，仅对有损格式有效）。
     */
    'thumb' => [
        'width' => 300,
        'height' => 300,
        'mode' => 'cover',
        'quality' => 90,
    ],

    /*
     * 水印默认参数
     * file：水印图片路径，为空时调用 watermark() 必须显式传入。
     * position：水印位置，支持 top-left、top、top-right、left、center、right、
     *           bottom-left、bottom、bottom-right。
     * offset_x/offset_y：相对定位位置的横向、纵向偏移（像素）。
     * opacity：不透明度，0-100，100 表示完全不透明。
     */
    'watermark' => [
        'file' => '',
        'position' => 'bottom-right',
        'offset_x' => 10,
        'offset_y' => 10,
        'opacity' => 100,
    ],
];