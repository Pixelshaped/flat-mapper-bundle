<?php
declare(strict_types=1);

namespace Pixelshaped\FlatMapperBundle\Tests\Examples\Valid\ClassAttributes;

use Pixelshaped\FlatMapperBundle\Mapping\Identifier;
use Pixelshaped\FlatMapperBundle\Mapping\NamePrefix;
use Pixelshaped\FlatMapperBundle\Mapping\ReferenceArray;

#[NamePrefix('author_')]
class AuthorDTO
{
    /**
     * @param array<BookDTO> $books
     */
    public function __construct(
        #[Identifier]
        public int $id,
        public string $name,
        #[ReferenceArray(BookDTO::class)]
        public array $books,
    ) {}
}
