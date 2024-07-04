<?php
declare(strict_types=1);

namespace Pixelshaped\FlatMapperBundle\Tests\Examples\Valid\ReferencesArray;

use Pixelshaped\FlatMapperBundle\Attributes\Identifier;
use Pixelshaped\FlatMapperBundle\Attributes\InboundPropertyName;
use Pixelshaped\FlatMapperBundle\Attributes\ReferencesArray;

class AuthorDTO
{
    /**
     * @param array<BookDTO> $leafs
     */
    public function __construct(
        #[Identifier]
        #[InboundPropertyName('author_id')]
        public int $id,
        #[InboundPropertyName('author_name')]
        public string $name,
        #[ReferencesArray(BookDTO::class)]
        public array $leafs,
    ) {}
}
