<?php
declare(strict_types=1);

namespace Pixelshaped\FlatMapperBundle;

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class PixelshapedFlatMapperBundle extends AbstractBundle
{
    /**
     * @param array<string, mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import('../config/services.php');

        $flatMapper = $builder->getDefinition('pixelshaped_flat_mapper.flat_mapper');
        if($config['cache_service'] !== null) {
            if(!is_string($config['cache_service'])) {
                throw new InvalidArgumentException('The "cache_service" option must be a string or null.');
            }

            $flatMapper->addMethodCall('setCacheService', [new Reference($config['cache_service'])]);
        }
        if($config['validate_mapping'] !== null) {
            $flatMapper->addMethodCall('setValidateMapping', [$config['validate_mapping']]);
        }
        if($config['mappings'] !== null) {
            $flatMapper->addMethodCall('setYamlMappings', [$config['mappings']]);
        }
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->booleanNode('validate_mapping')->defaultTrue()->end()
                ->scalarNode('cache_service')->defaultNull()->end()
                ->arrayNode('mappings')->defaultValue([])->normalizeKeys(false)->prototype('variable')->end()->end()
        ;
    }

}
