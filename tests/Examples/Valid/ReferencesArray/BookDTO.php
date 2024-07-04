<?php
declare(strict_types=1);

namespace Pixelshaped\FlatMapperBundle\Tests\Examples\Valid\ReferencesArray;

use Pixelshaped\FlatMapperBundle\Attributes\Identifier;
use Pixelshaped\FlatMapperBundle\Attributes\InboundPropertyName;

class BookDTO
{
    public function __construct(
        #[Identifier]
        #[InboundPropertyName('book_id')]
        public int $id,
        #[InboundPropertyName('book_name')]
        public string $name,
        #[InboundPropertyName('book_publisher_name')]
        public string $publisherName,
    ) {}
}
