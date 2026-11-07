<?php

declare(strict_types=1);

namespace Youvision\ThinkImager\Tests;

use PHPUnit\Framework\TestCase;
use think\App;
use Youvision\ThinkImager\facade\Imager as ImagerFacade;
use Youvision\ThinkImager\Imager;
use Youvision\ThinkImager\ImagerService;

final class ServiceTest extends TestCase
{
    private App $app;

    protected function setUp(): void
    {
        $this->app = new App(sys_get_temp_dir());

        (new ImagerService($this->app))->register();
    }

    public function testServiceMergesDefaultConfig(): void
    {
        $this->assertSame('gd', $this->app->config->get('imager.driver'));
        $this->assertSame('bottom-right', $this->app->config->get('imager.watermark.position'));
        $this->assertSame(300, $this->app->config->get('imager.thumb.width'));
    }

    public function testContainerResolvesImager(): void
    {
        $imager = $this->app->make(Imager::class);

        $this->assertInstanceOf(Imager::class, $imager);
        $this->assertSame('GD', $imager->driver()->id());
    }

    public function testFacadeUsesNewInstanceEveryTime(): void
    {
        $first = ImagerFacade::create(10, 10);
        $second = ImagerFacade::create(20, 20);

        $this->assertInstanceOf(Imager::class, $first);
        $this->assertInstanceOf(Imager::class, $second);
        $this->assertNotSame($first, $second);
        $this->assertSame(10, $first->width());
        $this->assertSame(20, $second->width());
    }

    public function testHelperReturnsFreshInstance(): void
    {
        $first = imager();
        $second = imager();

        $this->assertInstanceOf(Imager::class, $first);
        $this->assertNotSame($first, $second);
    }
}