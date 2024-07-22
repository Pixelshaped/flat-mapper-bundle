<?php
declare(strict_types=1);

namespace Pixelshaped\FlatMapperBundle\Tests\Functional;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Pixelshaped\FlatMapperBundle\FlatMapper;
use Pixelshaped\FlatMapperBundle\PixelshapedFlatMapperBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\HttpKernel\Kernel;

#[CoversClass(FlatMapper::class)]
#[CoversClass(PixelshapedFlatMapperBundle::class)]
class BundleFunctionalTest extends TestCase
{
    public function testServiceWiring(): void
    {
        $kernel = new PixelshapedFlatMapperTestingKernel('test', true);
        $kernel->boot();
        $container = $kernel->getContainer();
        $flatMapper = $container->get('pixelshaped_flat_mapper.flat_mapper');
        $this->assertInstanceOf(FlatMapper::class, $flatMapper);
    }
}

class PixelshapedFlatMapperTestingKernel extends Kernel
{
    public function registerBundles(): iterable
    {
        return [
            new PixelshapedFlatMapperBundle(),
        ];
    }
    public function registerContainerConfiguration(LoaderInterface $loader)
    {
    }
}