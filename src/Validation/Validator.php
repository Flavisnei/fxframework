<?php

declare(strict_types=1);

namespace Fx\Framework\Validation;

final class Validator
{
    /** @var array<string,list<string>> */
    private array $errors = [];

    /** @param array<string,mixed> $data @param array<string,string|list<string>> $rules */
    public function __construct(private readonly array $data, private readonly array $rules)
    {
        $this->validate();
    }

    /** @param array<string,mixed> $data @param array<string,string|list<string>> $rules */
    public static function make(array $data, array $rules): self { return new self($data, $rules); }
    public function fails(): bool { return $this->errors !== []; }
    public function passes(): bool { return !$this->fails(); }
    /** @return array<string,list<string>> */ public function errors(): array { return $this->errors; }

    /** @return array<string,mixed> */
    public function validated(): array
    {
        if ($this->fails()) { throw new ValidationException($this->errors); }
        return array_intersect_key($this->data, $this->rules);
    }

    private function validate(): void
    {
        foreach ($this->rules as $field => $definition) {
            $rules = is_array($definition) ? $definition : explode('|', $definition);
            $present = array_key_exists($field, $this->data);
            $value = $this->data[$field] ?? null;
            if (in_array('nullable', $rules, true) && ($value === null || $value === '')) { continue; }

            foreach ($rules as $rule) {
                [$name, $parameter] = array_pad(explode(':', $rule, 2), 2, null);
                if ($name === 'nullable') { continue; }
                $valid = match ($name) {
                    'required' => $present && $value !== null && $value !== '',
                    'string' => is_string($value),
                    'integer', 'int' => filter_var($value, FILTER_VALIDATE_INT) !== false,
                    'numeric' => is_numeric($value),
                    'boolean', 'bool' => is_bool($value) || in_array($value, [0, 1, '0', '1'], true),
                    'array' => is_array($value),
                    'email' => is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
                    'min' => $this->size($value) >= (float) $parameter,
                    'max' => $this->size($value) <= (float) $parameter,
                    'in' => in_array((string) $value, explode(',', (string) $parameter), true),
                    'confirmed' => ($this->data[$field . '_confirmation'] ?? null) === $value,
                    default => false,
                };
                if (!$valid) { $this->errors[$field][] = "O campo {$field} falhou na regra {$name}."; }
            }
        }
    }

    private function size(mixed $value): float
    {
        if (is_numeric($value)) { return (float) $value; }
        if (is_array($value)) { return count($value); }
        return is_string($value) ? mb_strlen($value) : 0;
    }
}
