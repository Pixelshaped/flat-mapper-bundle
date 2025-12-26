<?php
declare(strict_types=1);

namespace Pixelshaped\FlatMapperBundle\Tests\Examples\Valid\NameTransformation;

use Pixelshaped\FlatMapperBundle\Mapping\Identifier;
use Pixelshaped\FlatMapperBundle\Mapping\NameTransformation;

// Test that Identifier attribute takes precedence over NameTransformation
#[NameTransformation(removePrefix: 'car_', camelize: true)]
final readonly class CarDTO
{
    public function __construct(
        // Identifier with explicit column name should bypass transformation
        #[Identifier('vehicle_id')]
        public int $VehicleId,
        public string $Model,
        public string $Brand
    ) {
    }
}
