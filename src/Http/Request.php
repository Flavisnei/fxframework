<?php

declare(strict_types=1);

namespace Fx\Framework\Http;

use Illuminate\Http\Request as IlluminateRequest;
use Fx\Framework\Validation\Validator;

/**
 * Requisicao HTTP com a API input(), query(), file(), header() e validate-ready
 * do componente Illuminate.
 */
class Request extends IlluminateRequest
{
    /** @param array<string,string|list<string>> $rules @return array<string,mixed> */
    public function validate(array $rules): array
    {
        return Validator::make($this->all(), $rules)->validated();
    }
}
