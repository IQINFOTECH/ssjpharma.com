<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Minimal server-side validator. Rules per field are pipe-separated strings,
 * e.g. 'required|email|max:190'. Returns an error map (field => message).
 * Server-side validation is authoritative; client validation is UX only.
 */
final class Validator
{
    /** @var array<string,string> */
    private array $errors = [];

    /**
     * @param array<string,mixed> $data
     * @param array<string,string> $rules
     * @param array<string,string> $labels optional field => label
     */
    public function validate(array $data, array $rules, array $labels = []): bool
    {
        foreach ($rules as $field => $ruleset) {
            $value = $data[$field] ?? null;
            $label = $labels[$field] ?? ucfirst(str_replace('_', ' ', $field));

            foreach (explode('|', $ruleset) as $rule) {
                if ($rule === '') {
                    continue;
                }
                [$name, $param] = array_pad(explode(':', $rule, 2), 2, null);

                $ok = match ($name) {
                    'required' => is_string($value) ? trim($value) !== '' : !empty($value),
                    'email'    => $value === null || $value === '' || filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
                    'max'      => $value === null || mb_strlen((string) $value) <= (int) $param,
                    'min'      => $value === null || mb_strlen((string) $value) >= (int) $param,
                    'in'       => $value === null || $value === '' || in_array((string) $value, explode(',', (string) $param), true),
                    'accepted' => in_array($value, ['1', 1, true, 'true', 'on', 'yes'], true),
                    'url'      => $value === null || $value === '' || filter_var($value, FILTER_VALIDATE_URL) !== false,
                    'phone'    => $value === null || $value === '' || preg_match('/^[0-9+()\-\s]{6,30}$/', (string) $value) === 1,
                    default    => true,
                };

                if (!$ok && !isset($this->errors[$field])) {
                    $this->errors[$field] = $this->message($name, $label, $param);
                }
            }
        }

        return $this->errors === [];
    }

    /** @return array<string,string> */
    public function errors(): array
    {
        return $this->errors;
    }

    private function message(string $rule, string $label, ?string $param): string
    {
        return match ($rule) {
            'required' => "{$label} is required.",
            'email'    => "Please enter a valid email address.",
            'max'      => "{$label} must not exceed {$param} characters.",
            'min'      => "{$label} must be at least {$param} characters.",
            'accepted' => "{$label} must be accepted.",
            'url'      => "Please enter a valid URL.",
            'phone'    => "Please enter a valid phone number.",
            'in'       => "Please choose a valid option for {$label}.",
            default    => "{$label} is invalid.",
        };
    }
}
