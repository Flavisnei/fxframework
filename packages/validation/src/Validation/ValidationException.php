<?php

declare(strict_types=1);

namespace Fx\Framework\Validation;

use RuntimeException;

final class ValidationException extends RuntimeException
{
    /** @param array<string,list<string>> $errors */
    public function __construct(private readonly array $errors)
    {
        parent::__construct('Os dados informados sao invalidos.');
    }

    /** @return array<string,list<string>> */
    public function errors(): array { return $this->errors; }
}
