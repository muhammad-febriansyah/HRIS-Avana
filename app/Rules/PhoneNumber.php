<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * An Indonesian mobile/WhatsApp number: `08xxxxxxxxxx` or `+628xxxxxxxxxx`,
 * digits only after the prefix. Public forms (company inquiry, self-serve
 * signup, partner application) collect this with nothing else guarding it,
 * so free text like "Voluptatibus possimu" was passing straight through.
 */
final class PhoneNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^(\+?62|0)8[1-9][0-9]{6,10}$/', $value)) {
            $fail('Nomor WhatsApp tidak valid. Gunakan format 08xxxxxxxxxx atau +628xxxxxxxxxx.');
        }
    }
}
