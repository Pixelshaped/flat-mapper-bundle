<?php
declare(strict_types=1);

namespace Pixelshaped\FlatMapperBundle\Tests\Examples\Valid\ClassAttributes;

use Pixelshaped\FlatMapperBundle\Mapping\Identifier;
use Pixelshaped\FlatMapperBundle\Mapping\NameTransformation;

#[NameTransformation(removePrefix: 'book_', camelize: true)]
class BookDTO
{
    public function __construct(
        #[Identifier]
        public int $id,
        public string $name,
        public string $publisherName,
    ) {}
}
