<?php
declare(strict_types=1);

namespace Pixelshaped\FlatMapperBundle\Tests\Examples\Valid\ReferenceArray;

use Pixelshaped\FlatMapperBundle\Mapping\Identifier;
use Pixelshaped\FlatMapperBundle\Mapping\ReferenceArray;
use Pixelshaped\FlatMapperBundle\Mapping\Scalar;

class AuthorDTO
{
    /**
     * @param array<BookDTO> $books
     */
    public function __construct(
        #[Identifier]
        #[Scalar('author_id')]
        public int $id,
        #[Scalar('author_name')]
        public string $name,
        #[ReferenceArray(BookDTO::class)]
        public array $books,
    ) {}
}
