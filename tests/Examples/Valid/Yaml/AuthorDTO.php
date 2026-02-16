<?php
declare(strict_types=1);

namespace Pixelshaped\FlatMapperBundle\Tests\Examples\Valid\Yaml;

class AuthorDTO
{
    /**
     * @param array<BookDTO> $books
     */
    public function __construct(
        public int $id,
        public string $name,
        public array $books,
    ) {}
}
