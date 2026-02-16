<?php
declare(strict_types=1);

namespace Pixelshaped\FlatMapperBundle\Tests\Examples\Valid\Yaml;

class BookDTO
{
    public function __construct(
        public int $id,
        public string $name,
        public string $publisherName,
    ) {}
}
