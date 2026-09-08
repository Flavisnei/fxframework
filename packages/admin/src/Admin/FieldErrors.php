<?php
declare(strict_types=1);
namespace Fx\Framework\Admin;

use Symfony\Component\HttpKernel\Exception\HttpException;

/** Mensagens publicas por nome de campo; nunca incluir valores secretos. */
final class FieldErrors extends HttpException
{
    public function __construct(public readonly array $errors, int $status = 422, string $message = 'Revise os campos indicados.')
    {
        parent::__construct($status, $message);
    }
}
