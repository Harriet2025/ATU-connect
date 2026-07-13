<?php
namespace Core;

// =========================================================
// VALIDATOR — simple rule-based input validation
// =========================================================
class Validator
{
    private array $errors = [];

    public function validate(array $data, array $rules): bool
    {
        foreach ($rules as $field => $ruleString) {
            $rulesArr = explode('|', $ruleString);
            $value = $data[$field] ?? null;

            foreach ($rulesArr as $rule) {
                [$name, $param] = array_pad(explode(':', $rule, 2), 2, null);

                switch ($name) {
                    case 'required':
                        if ($value === null || $value === '') {
                            $this->addError($field, "$field is required.");
                        }
                        break;
                    case 'email':
                        if ($value && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            $this->addError($field, "$field must be a valid email.");
                        }
                        break;
                    case 'min':
                        if ($value !== null && strlen((string)$value) < (int)$param) {
                            $this->addError($field, "$field must be at least $param characters.");
                        }
                        break;
                    case 'max':
                        if ($value !== null && strlen((string)$value) > (int)$param) {
                            $this->addError($field, "$field must not exceed $param characters.");
                        }
                        break;
                    case 'numeric':
                        if ($value !== null && $value !== '' && !is_numeric($value)) {
                            $this->addError($field, "$field must be numeric.");
                        }
                        break;
                    case 'in':
                        $allowed = explode(',', $param ?? '');
                        if ($value !== null && $value !== '' && !in_array($value, $allowed, true)) {
                            $this->addError($field, "$field must be one of: $param.");
                        }
                        break;
                    case 'date':
                        if ($value && strtotime($value) === false) {
                            $this->addError($field, "$field must be a valid date.");
                        }
                        break;
                }
            }
        }

        return empty($this->errors);
    }

    public function errors(): array
    {
        return $this->errors;
    }

    private function addError(string $field, string $message): void
    {
        $this->errors[$field][] = $message;
    }

    public static function sanitize(string $value): string
    {
        return htmlspecialchars(strip_tags(trim($value)), ENT_QUOTES, 'UTF-8');
    }
}
