<?php

declare(strict_types=1);

namespace Youvision\ThinkImager;

use think\Service;

/**
 * ThinkPHP 8 服务注册
 */
class ImagerService extends Service
{
    public function register(): void
    {
        $config = Imager::mergeDefaults((array) $this->app->config->get('imager', []));
        $this->app->config->set($config, 'imager');

        $this->app->bind(Imager::class, function (): Imager {
            return new Imager((array) $this->app->config->get('imager', []));
        });

        $this->app->bind('imager', Imager::class);
    }
}