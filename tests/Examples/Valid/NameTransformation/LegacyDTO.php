<?php
declare(strict_types=1);

namespace Pixelshaped\FlatMapperBundle\Tests\Examples\Valid\NameTransformation;

use Pixelshaped\FlatMapperBundle\Mapping\NameTransformation;

// Test backward compatibility with old parameter names
#[NameTransformation(removePrefix: 'legacy_', camelize: true)]
final readonly class LegacyDTO
{
    public function __construct(
        #[\Pixelshaped\FlatMapperBundle\Mapping\Identifier]
        public int $Id,
        public string $Name
    ) {
    }
}
