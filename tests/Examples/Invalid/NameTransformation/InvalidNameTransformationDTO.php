<?php
declare(strict_types=1);

namespace Pixelshaped\FlatMapperBundle\Tests\Examples\Invalid\NameTransformation;

use Pixelshaped\FlatMapperBundle\Mapping\Identifier;
use Pixelshaped\FlatMapperBundle\Mapping\NameTransformation;

#[Identifier('id')]
// @phpstan-ignore argument.unknown
#[NameTransformation(invalidParameter: 'test')]
final readonly class InvalidNameTransformationDTO
{
    public function __construct(
        public int $id,
        public string $name
    ) {
    }
}
