<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Regla mínima de seguridad de contraseñas:
 *   - Mínimo 10 caracteres.
 *   - No estar en el listado de contraseñas más comunes (top breach lists).
 *   - No ser solo dígitos (123456789012 no califica).
 *   - No coincidir con campos del usuario (nombre, email) si se pasan.
 *
 * Uso:
 *   'password' => ['required', new PasswordSegura(['nombre_completo', 'email'], $request->all())]
 */
class PasswordSegura implements ValidationRule
{
    /**
     * Top de contraseñas más usadas según breach datasets (RockYou, HIBP).
     * Lista corta y representativa — cubre el 80% del tráfico de bots.
     */
    private const COMUNES = [
        '123456', '12345678', '123456789', '1234567890', 'qwerty', 'password',
        'password1', 'password123', 'admin', 'admin123', 'administrator',
        'welcome', 'welcome1', 'letmein', 'monkey', 'iloveyou', 'qwerty123',
        '123123123', 'abcdef', 'abc123', 'master', 'sunshine', 'princess',
        'football', 'baseball', 'dragon', 'shadow', 'superman', 'batman',
        'trustno1', '1q2w3e4r', 'qwertyuiop', 'asdfghjkl', 'zxcvbnm',
        'crecer', 'crecer123', 'reproduccion', 'secretaria',
    ];

    /** Campos del modelo a comparar (ej: nombre, email) — la pass no puede ser igual o contenerlos. */
    private array $camposProhibidos = [];

    /** Valores actuales de los campos para comparar. */
    private array $valoresUsuario = [];

    public function __construct(array $camposProhibidos = [], array $valoresUsuario = [])
    {
        $this->camposProhibidos = $camposProhibidos;
        $this->valoresUsuario   = $valoresUsuario;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value)) {
            $fail('La contraseña debe ser una cadena.');
            return;
        }

        if (strlen($value) < 10) {
            $fail('La contraseña debe tener al menos 10 caracteres.');
            return;
        }

        if (ctype_digit($value)) {
            $fail('La contraseña no puede ser solo números — sumá letras.');
            return;
        }

        $lower = strtolower($value);
        foreach (self::COMUNES as $comun) {
            if ($lower === $comun) {
                $fail('Esa contraseña es demasiado común — elegí algo más difícil de adivinar.');
                return;
            }
        }

        foreach ($this->camposProhibidos as $campo) {
            $valor = $this->valoresUsuario[$campo] ?? null;
            if ($valor && strlen($valor) >= 4) {
                if (str_contains($lower, strtolower($valor))) {
                    $fail("La contraseña no puede contener tu {$campo}.");
                    return;
                }
            }
        }
    }
}
