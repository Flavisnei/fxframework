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
            $required = in_array('required', $rules, true);
            if ($required && (!$present || $value === null || $value === [] || (is_string($value) && trim($value) === ''))) {
                $this->errors[$field][] = "O campo {$field} falhou na regra required.";
                continue;
            }
            if (!$present) { continue; }
            if (in_array('nullable', $rules, true) && ($value === null || $value === '')) { continue; }
            $numeric = array_intersect(['integer', 'int', 'numeric'], $rules) !== [];

            foreach ($rules as $rule) {
                [$name, $parameter] = array_pad(explode(':', $rule, 2), 2, null);
                if ($name === 'nullable' || $name === 'required') { continue; }
                $valid = match ($name) {
                    'string' => is_string($value),
                    'integer', 'int' => (is_int($value) || is_string($value)) && filter_var($value, FILTER_VALIDATE_INT) !== false,
                    'numeric' => is_numeric($value),
                    'boolean', 'bool' => is_bool($value) || in_array($value, [0, 1, '0', '1'], true),
                    'array' => is_array($value),
                    'email' => is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
                    'min' => $this->size($value, $numeric) !== null && $this->size($value, $numeric) >= (float) $parameter,
                    'max' => $this->size($value, $numeric) !== null && $this->size($value, $numeric) <= (float) $parameter,
                    'in' => is_scalar($value) && in_array((string) $value, explode(',', (string) $parameter), true),
                    'confirmed' => ($this->data[$field . '_confirmation'] ?? null) === $value,
                    default => false,
                };
                if (!$valid) { $this->errors[$field][] = "O campo {$field} falhou na regra {$name}."; }
            }
        }
    }

    private function size(mixed $value, bool $numeric): ?float
    {
        if ($numeric) { return is_numeric($value) ? (float) $value : null; }
        if (is_array($value)) { return count($value); }
        if (is_string($value)) { return mb_strlen($value, 'UTF-8'); }
        return is_int($value) || is_float($value) ? (float) $value : null;
    }
}
