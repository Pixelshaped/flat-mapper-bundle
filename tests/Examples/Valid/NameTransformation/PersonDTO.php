<?php
declare(strict_types=1);

namespace Pixelshaped\FlatMapperBundle\Tests\Examples\Valid\NameTransformation;

use Pixelshaped\FlatMapperBundle\Mapping\Identifier;
use Pixelshaped\FlatMapperBundle\Mapping\NameTransformation;

#[Identifier('person_id')]
#[NameTransformation(removePrefix: 'person_')]
final readonly class PersonDTO
{
    public function __construct(
        public int $id,
        public string $name,
        public int $age
    ) {
    }
}
