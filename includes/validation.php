<?php
/**
 * KhuntaLocal — Server-side validation.
 *
 * Client-side checks are only hints; this is the authoritative validation.
 * Usage:
 *     $v = new Validator($_POST);
 *     $v->required('title', 'Headline')->max('title', 220);
 *     $v->required('email')->email('email');
 *     if ($v->fails()) { $errors = $v->errors(); }
 */

declare(strict_types=1);

class Validator
{
    /** @var array<string,mixed> */
    private array $data;

    /** @var array<string,string> field => first error message */
    private array $errors = [];

    /** @param array<string,mixed> $data */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    private function value(string $field): string
    {
        $v = $this->data[$field] ?? '';
        return is_string($v) ? trim($v) : (is_scalar($v) ? trim((string) $v) : '');
    }

    private function label(string $field, ?string $label): string
    {
        return $label ?? ucwords(str_replace(['_', '-'], ' ', $field));
    }

    private function addError(string $field, string $message): void
    {
        // Keep only the first error per field.
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = $message;
        }
    }

    public function required(string $field, ?string $label = null): self
    {
        if ($this->value($field) === '') {
            $this->addError($field, $this->label($field, $label) . ' is required.');
        }
        return $this;
    }

    public function min(string $field, int $min, ?string $label = null): self
    {
        $len = mb_strlen($this->value($field));
        if ($len > 0 && $len < $min) {
            $this->addError($field, $this->label($field, $label) . " must be at least {$min} characters.");
        }
        return $this;
    }

    public function max(string $field, int $max, ?string $label = null): self
    {
        if (mb_strlen($this->value($field)) > $max) {
            $this->addError($field, $this->label($field, $label) . " must not exceed {$max} characters.");
        }
        return $this;
    }

    public function email(string $field, ?string $label = null): self
    {
        $v = $this->value($field);
        if ($v !== '' && !filter_var($v, FILTER_VALIDATE_EMAIL)) {
            $this->addError($field, 'Please enter a valid email address.');
        }
        return $this;
    }

    public function url(string $field, ?string $label = null): self
    {
        $v = $this->value($field);
        if ($v !== '' && !filter_var($v, FILTER_VALIDATE_URL)) {
            $this->addError($field, $this->label($field, $label) . ' must be a valid URL (including http:// or https://).');
        }
        return $this;
    }

    public function in(string $field, array $allowed, ?string $label = null): self
    {
        $v = $this->value($field);
        if ($v !== '' && !in_array($v, array_map('strval', $allowed), true)) {
            $this->addError($field, 'Please choose a valid ' . strtolower($this->label($field, $label)) . '.');
        }
        return $this;
    }

    public function integer(string $field, ?string $label = null): self
    {
        $v = $this->value($field);
        if ($v !== '' && filter_var($v, FILTER_VALIDATE_INT) === false) {
            $this->addError($field, $this->label($field, $label) . ' must be a number.');
        }
        return $this;
    }

    public function matches(string $field, string $otherField, ?string $label = null): self
    {
        if ($this->value($field) !== $this->value($otherField)) {
            $this->addError($field, $this->label($field, $label) . ' does not match.');
        }
        return $this;
    }

    public function phone(string $field, ?string $label = null): self
    {
        $v = $this->value($field);
        if ($v !== '' && !preg_match('/^[0-9+][0-9\s\-]{6,19}$/', $v)) {
            $this->addError($field, 'Please enter a valid phone number.');
        }
        return $this;
    }

    public function accepted(string $field, ?string $label = null): self
    {
        $v = $this->value($field);
        if (!in_array(strtolower($v), ['1', 'true', 'on', 'yes'], true)) {
            $this->addError($field, 'Please accept the ' . strtolower($this->label($field, $label)) . '.');
        }
        return $this;
    }

    /** Add an error from outside the fluent chain (e.g. "email already taken"). */
    public function add(string $field, string $message): self
    {
        $this->addError($field, $message);
        return $this;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function passes(): bool
    {
        return $this->errors === [];
    }

    /** @return array<string,string> */
    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(): ?string
    {
        return $this->errors === [] ? null : reset($this->errors);
    }
}
