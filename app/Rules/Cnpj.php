<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class Cnpj implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $cnpj = preg_replace('/\D/', '', $value);

        if (strlen($cnpj) !== 14) {
            $fail('O CNPJ deve ter 14 dígitos.');
            return;
        }

        if (preg_match('/^(\d)\1{13}$/', $cnpj)) {
            $fail('O CNPJ informado é inválido.');
            return;
        }

        $weights1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $weights2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

        // First check digit
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += (int) $cnpj[$i] * $weights1[$i];
        }
        $digit1 = ($sum % 11) < 2 ? 0 : 11 - ($sum % 11);

        // Second check digit
        $sum = 0;
        for ($i = 0; $i < 13; $i++) {
            $sum += (int) $cnpj[$i] * $weights2[$i];
        }
        $digit2 = ($sum % 11) < 2 ? 0 : 11 - ($sum % 11);

        if ((int) $cnpj[12] !== $digit1 || (int) $cnpj[13] !== $digit2) {
            $fail('O CNPJ informado é inválido. Dígitos verificadores incorretos. Ex válido: 11.222.333/0001-81');
            return;
        }
    }
}
