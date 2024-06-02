<?php
declare(strict_types=1);

namespace Pixelshaped\FlatMapperBundle;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class PixelshapedFlatMapperBundle extends AbstractBundle
{
    /**
     * @param array<string> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import('../config/services.xml');

        $flatMapper = $builder->getDefinition('pixelshaped_flat_mapper.flat_mapper');
        if($config['cache_service'] !== null) {
            $flatMapper->addMethodCall('setCacheService', [new Reference($config['cache_service'])]);
        }
        if($config['validate_mapping'] !== null) {
            $flatMapper->addMethodCall('setValidateMapping', [$config['validate_mapping']]);
        }
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode() // @phpstan-ignore-line
            ->children()
                ->booleanNode('validate_mapping')->defaultFalse()->end()
                ->scalarNode('cache_service')->defaultNull()->end()
        ;
    }

}
