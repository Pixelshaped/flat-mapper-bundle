<?php
declare(strict_types=1);

namespace Pixelshaped\FlatMapperBundle\Exception;

class MappingCreationException extends \RuntimeException
{
    public function __construct(
        string $message = "",
        int $code = 0,
        \Throwable|null $previous = null
    ) {
        parent::__construct('An error occurred during mapping creation: '.$message, $code, $previous);
    }
}