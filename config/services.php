<?php
declare(strict_types=1);

use Pixelshaped\FlatMapperBundle\FlatMapper;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('pixelshaped_flat_mapper.flat_mapper', FlatMapper::class)->public();
    $services->alias(FlatMapper::class, 'pixelshaped_flat_mapper.flat_mapper');
};
