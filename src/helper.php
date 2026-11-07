<?php

declare(strict_types=1);

use Youvision\ThinkImager\Imager;

if (!function_exists('imager')) {
    /**
     * 获取一个全新的图片处理实例。
     *
     * @param array|null $config 自定义配置，为空时使用容器中的 imager 配置
     */
    function imager(?array $config = null): Imager
    {
        if ($config !== null) {
            return new Imager($config);
        }

        if (class_exists(\think\Container::class)) {
            $container = \think\Container::getInstance();

            if ($container instanceof \think\Container) {
                return $container->make(Imager::class, [], true);
            }
        }

        return new Imager();
    }
}