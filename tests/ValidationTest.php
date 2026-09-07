<?php

declare(strict_types=1);

namespace Fx\Framework\Tests;

use Fx\Framework\Validation\ValidationException;
use Fx\Framework\Validation\Validator;
use PHPUnit\Framework\TestCase;

final class ValidationTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\DataProvider('validationCases')]
    public function testRuleCombinations(array $data, string $rules, bool $passes): void
    {
        self::assertSame($passes, Validator::make($data, ['value' => $rules])->passes());
    }

    public static function validationCases(): array
    {
        return [
            'required nullable absent' => [[], 'required|nullable|string', false],
            'required nullable null' => [['value' => null], 'nullable|required', false],
            'required whitespace' => [['value' => '  '], 'required', false],
            'required empty array' => [['value' => []], 'required|array', false],
            'required zero' => [['value' => 0], 'required|integer', true],
            'required false' => [['value' => false], 'required|boolean', true],
            'optional absent' => [[], 'string|min:3', true],
            'nullable null' => [['value' => null], 'nullable|string', true],
            'nullable empty string retained' => [['value' => ''], 'nullable|integer', true],
            'non nullable null' => [['value' => null], 'string', false],
            'numeric string length' => [['value' => '999'], 'string|max:5', true],
            'numeric string minimum length' => [['value' => '999'], 'string|min:5', false],
            'numeric value' => [['value' => '999'], 'numeric|max:5', false],
            'integer value' => [['value' => '12'], 'integer|min:10', true],
            'true is not integer' => [['value' => true], 'integer', false],
            'unicode length' => [['value' => 'ação'], 'string|min:4|max:4', true],
            'array length' => [['value' => [1, 2]], 'array|min:2|max:2', true],
            'array in does not cast' => [['value' => []], 'in:Array', false],
        ];
    }

    public function testItReturnsOnlyValidatedFields(): void
    {
        $data = Validator::make(
            ['name' => 'Maria', 'email' => 'maria@example.com', 'admin' => true],
            ['name' => 'required|string|min:3', 'email' => 'required|email']
        )->validated();

        self::assertSame(['name' => 'Maria', 'email' => 'maria@example.com'], $data);
    }

    public function testItThrowsStructuredErrors(): void
    {
        try {
            Validator::make(['email' => 'invalid'], ['name' => 'required', 'email' => 'email'])->validated();
            self::fail('ValidationException esperada.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('name', $exception->errors());
            self::assertArrayHasKey('email', $exception->errors());
        }
    }
}
