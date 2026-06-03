<?php

namespace App\Helpers;

class Validation
{
    private array $errors = [];
    private array $data   = [];

    public function __construct(array $input)
    {
        $this->data = $input;
    }

    public function required(string $field, ?string $label = null): self
    {
        $label = $label ?? $field;
        if (empty($this->data[$field]) && $this->data[$field] !== '0') {
            $this->errors[$field][] = t('validation.required', ['field' => $label]);
        }
        return $this;
    }

    public function maxLength(string $field, int $max, ?string $label = null): self
    {
        $label = $label ?? $field;
        if (isset($this->data[$field]) && mb_strlen($this->data[$field]) > $max) {
            $this->errors[$field][] = t('validation.max_length', ['field' => $label, 'max' => $max]);
        }
        return $this;
    }

    public function minLength(string $field, int $min, ?string $label = null): self
    {
        $label = $label ?? $field;
        if (isset($this->data[$field]) && mb_strlen($this->data[$field]) < $min) {
            $this->errors[$field][] = t('validation.min_length', ['field' => $label, 'min' => $min]);
        }
        return $this;
    }

    public function email(string $field): self
    {
        if (!empty($this->data[$field]) && !filter_var($this->data[$field], FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field][] = t('validation.email');
        }
        return $this;
    }

    public function numeric(string $field, ?string $label = null): self
    {
        $label = $label ?? $field;
        if (!empty($this->data[$field]) && !is_numeric($this->data[$field])) {
            $this->errors[$field][] = t('validation.numeric', ['field' => $label]);
        }
        return $this;
    }

    public function min(string $field, float $min, ?string $label = null): self
    {
        $label = $label ?? $field;
        if (isset($this->data[$field]) && is_numeric($this->data[$field]) && (float)$this->data[$field] < $min) {
            $this->errors[$field][] = t('validation.min_value', ['field' => $label, 'min' => $min]);
        }
        return $this;
    }

    public function inList(string $field, array $allowed, ?string $label = null): self
    {
        $label = $label ?? $field;
        if (!empty($this->data[$field]) && !in_array($this->data[$field], $allowed, true)) {
            $this->errors[$field][] = t('validation.invalid_option', ['field' => $label]);
        }
        return $this;
    }

    public function passes(): bool
    {
        return empty($this->errors);
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function get(string $field, mixed $default = null): mixed
    {
        return $this->data[$field] ?? $default;
    }

    public function all(): array
    {
        return $this->data;
    }
}
