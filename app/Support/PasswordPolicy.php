<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Reasonable password policy (Phase 2 §8): a sensible minimum length and a few
 * weak-password guards — no arbitrary complexity theatre. Never logs passwords.
 */
final class PasswordPolicy
{
    public static function validate(string $password, int $minLength = 10, ?string $email = null): ?string
    {
        $len = strlen($password);
        if ($len < $minLength) {
            return "Password must be at least {$minLength} characters.";
        }
        if ($len > 200) {
            return 'Password is too long.';
        }
        if (preg_match('/^(.)\1+$/', $password)) {
            return 'Password must not be a single repeated character.';
        }
        if (ctype_digit($password)) {
            return 'Password must not be entirely numeric.';
        }
        if ($email !== null && $email !== '') {
            $local = strtolower(explode('@', $email)[0]);
            if ($local !== '' && strtolower($password) === $local) {
                return 'Password must not match your email name.';
            }
        }
        return null;
    }
}
