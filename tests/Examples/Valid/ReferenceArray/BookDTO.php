<?php
declare(strict_types=1);

namespace Pixelshaped\FlatMapperBundle\Tests\Examples\Valid\ReferenceArray;

use Pixelshaped\FlatMapperBundle\Mapping\Identifier;
use Pixelshaped\FlatMapperBundle\Mapping\Scalar;

class BookDTO
{
    public function __construct(
        #[Identifier]
        #[Scalar('book_id')]
        public int $id,
        #[Scalar('book_name')]
        public string $name,
        #[Scalar('book_publisher_name')]
        public string $publisherName,
    ) {}
}
