<?php
declare(strict_types=1);

namespace Pixelshaped\FlatMapperBundle\Tests\Examples\Valid\NameTransformation;

use Pixelshaped\FlatMapperBundle\Mapping\Identifier;
use Pixelshaped\FlatMapperBundle\Mapping\NameTransformation;
use Pixelshaped\FlatMapperBundle\Mapping\Scalar;

// Test that Scalar attribute takes precedence over NameTransformation
#[NameTransformation(columnPrefix: 'acc_')]
final readonly class AccountDTO
{
    public function __construct(
        #[Identifier]
        public int $id,
        public string $name,
        // This should use 'account_balance' not 'acc_balance' because Scalar takes precedence
        #[Scalar('account_balance')]
        public float $balance
    ) {
    }
}
