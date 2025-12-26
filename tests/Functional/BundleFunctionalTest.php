<?php
declare(strict_types=1);

namespace Pixelshaped\FlatMapperBundle\Tests\Functional;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Pixelshaped\FlatMapperBundle\FlatMapper;
use Pixelshaped\FlatMapperBundle\PixelshapedFlatMapperBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

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

    public function testServiceWiringWithCacheService(): void
    {
        $kernel = new PixelshapedFlatMapperTestingKernelWithCache('test', true);
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

class PixelshapedFlatMapperTestingKernelWithCache extends Kernel
{
    public function registerBundles(): iterable
    {
        return [
            new PixelshapedFlatMapperBundle(),
        ];
    }
    public function registerContainerConfiguration(LoaderInterface $loader)
    {
        $loader->load(function ($container) {
            // Register a mock cache service
            $container->register('cache.app', MockCacheAdapter::class);

            $container->loadFromExtension('pixelshaped_flat_mapper', [
                'cache_service' => 'cache.app',
                'validate_mapping' => false,
            ]);
        });
    }
}

class MockCacheAdapter implements CacheInterface
{
    /**
     * @param array<string, mixed>|null $metadata
     */
    public function get(string $key, callable $callback, ?float $beta = null, ?array &$metadata = null): mixed
    {
        return $callback($this->createMockItem(), false);
    }

    public function delete(string $key): bool
    {
        return true;
    }

    private function createMockItem(): ItemInterface
    {
        return new class implements ItemInterface {
            public function getKey(): string { return 'test'; }
            public function get(): mixed { return null; }
            public function isHit(): bool { return false; }
            public function set(mixed $value): static { return $this; }
            public function expiresAt(?\DateTimeInterface $expiration): static { return $this; }
            public function expiresAfter(int|\DateInterval|null $time): static { return $this; }
            public function tag(iterable|string $tags): static { return $this; }
            /** @return array<string, mixed> */
            public function getMetadata(): array { return []; }
        };
    }
}