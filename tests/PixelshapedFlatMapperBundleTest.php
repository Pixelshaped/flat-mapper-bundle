<?php
declare(strict_types=1);

namespace Pixelshaped\FlatMapperBundle\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Pixelshaped\FlatMapperBundle\FlatMapper;
use Pixelshaped\FlatMapperBundle\PixelshapedFlatMapperBundle;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Config\Definition\Loader\DefinitionFileLoader;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(PixelshapedFlatMapperBundle::class)]
class PixelshapedFlatMapperBundleTest extends TestCase
{
    public function testLoadExtensionRegistersServiceAndAppliesConfiguration(): void
    {
        $bundle = new PixelshapedFlatMapperBundle();
        $containerBuilder = new ContainerBuilder();

        $mappings = [
            'App\\Dto\\FooDTO' => [
                'properties' => [
                    'id' => ['Identifier' => 'foo_id'],
                ],
            ],
        ];

        $bundle->loadExtension(
            [
                'cache_service' => 'cache.app',
                'validate_mapping' => false,
                'mappings' => $mappings,
            ],
            $this->createContainerConfigurator($containerBuilder),
            $containerBuilder
        );

        $this->assertTrue($containerBuilder->hasDefinition('pixelshaped_flat_mapper.flat_mapper'));
        $definition = $containerBuilder->getDefinition('pixelshaped_flat_mapper.flat_mapper');
        $this->assertSame(FlatMapper::class, $definition->getClass());

        $callsByMethod = [];
        foreach ($definition->getMethodCalls() as [$method, $arguments]) {
            $callsByMethod[$method] = $arguments;
        }

        $this->assertArrayHasKey('setCacheService', $callsByMethod);
        $this->assertArrayHasKey('setValidateMapping', $callsByMethod);
        $this->assertArrayHasKey('setYamlMappings', $callsByMethod);

        $this->assertInstanceOf(Reference::class, $callsByMethod['setCacheService'][0]);
        $this->assertSame('cache.app', (string)$callsByMethod['setCacheService'][0]);
        $this->assertSame([false], $callsByMethod['setValidateMapping']);
        $this->assertSame([$mappings], $callsByMethod['setYamlMappings']);
    }

    public function testConfigureDefinesExpectedDefaultOptions(): void
    {
        $bundle = new PixelshapedFlatMapperBundle();
        $treeBuilder = new TreeBuilder('pixelshaped_flat_mapper');
        $definitionLoader = new DefinitionFileLoader(
            $treeBuilder,
            new FileLocator([dirname(__DIR__)])
        );

        $bundle->configure(new DefinitionConfigurator(
            $treeBuilder,
            $definitionLoader,
            __FILE__,
            __FILE__
        ));

        $processor = new Processor();
        /** @var array{validate_mapping: bool, cache_service: null|string, mappings: array<mixed>} $processed */
        $processed = $processor->process($treeBuilder->buildTree(), [[]]);

        $this->assertTrue($processed['validate_mapping']);
        $this->assertNull($processed['cache_service']);
        $this->assertSame([], $processed['mappings']);
    }

    public function testLoadExtensionWithNonStringCacheServiceAsserts(): void
    {
        $bundle = new PixelshapedFlatMapperBundle();
        $containerBuilder = new ContainerBuilder();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "cache_service" option must be a string or null.');

        $bundle->loadExtension(
            [
                'cache_service' => 123,
                'validate_mapping' => true,
                'mappings' => [],
            ],
            $this->createContainerConfigurator($containerBuilder),
            $containerBuilder
        );
    }

    private function createContainerConfigurator(ContainerBuilder $containerBuilder): ContainerConfigurator
    {
        $instanceof = [];
        $projectDirectory = dirname(__DIR__);
        $bundlePath = $projectDirectory.'/src/PixelshapedFlatMapperBundle.php';

        return new ContainerConfigurator(
            $containerBuilder,
            new PhpFileLoader($containerBuilder, new FileLocator([$projectDirectory])),
            $instanceof,
            $bundlePath,
            $bundlePath,
            'test'
        );
    }
}
