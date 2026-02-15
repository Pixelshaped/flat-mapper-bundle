<?php
declare(strict_types=1);

namespace Pixelshaped\FlatMapperBundle\Tests\Examples\Valid\NamedArguments;

use Pixelshaped\FlatMapperBundle\Mapping\Identifier;
use Pixelshaped\FlatMapperBundle\Mapping\ReferenceArray;
use Pixelshaped\FlatMapperBundle\Mapping\Scalar;
use Pixelshaped\FlatMapperBundle\Mapping\ScalarArray;

#[Identifier(mappedPropertyName: 'parent_id')]
final class NamedArgumentsParentDTO
{
    /**
     * @param array<NamedArgumentsChildDTO> $children
     * @param array<int> $tagIds
     */
    public function __construct(
        #[Scalar(mappedPropertyName: 'parent_name')]
        public string $name,
        #[ReferenceArray(referenceClassName: NamedArgumentsChildDTO::class)]
        public array $children,
        #[ScalarArray(mappedPropertyName: 'tag_id')]
        public array $tagIds,
    ) {}
}
