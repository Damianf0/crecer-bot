<?php

namespace App\Livewire;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Login extends Component
{
    public string $email = '';
    public string $password = '';
    public string $error = '';

    public function intentarLogin(): void
    {
        $this->error = '';

        $email = trim(strtolower($this->email));
        if (!$email || !$this->password) {
            $this->error = 'Ingresá email y contraseña.';
            return;
        }

        // Buscar usuario por email (no por activo) para poder distinguir bloqueo de credencial mala.
        $user = User::where('email', $email)->first();

        // Si está bloqueado por intentos previos, cortar acá sin tocar la contraseña.
        if ($user && $user->estaBloqueado()) {
            $mins = $user->minutosBloqueoRestantes();
            $this->error = "Demasiados intentos fallidos. Volvé a intentar en {$mins} minuto" . ($mins !== 1 ? 's' : '') . '.';
            return;
        }

        // Intentar autenticar (password + activo).
        $ok = Auth::attempt(
            ['email' => $email, 'password' => $this->password, 'activo' => true],
            remember: true
        );

        if (!$ok) {
            // Si el usuario existe Y está activo, contar el intento fallido.
            // Si no existe o está inactivo, NO contamos (evita enumeración de usuarios).
            if ($user && $user->activo) {
                $bloqueado = $user->intentoFallido();
                if ($bloqueado) {
                    $this->error = 'Demasiados intentos fallidos. La cuenta queda bloqueada por '
                        . User::BLOQUEO_MINUTOS . ' minutos.';
                    return;
                }
            }
            // Mensaje genérico sin filtrar info ("usuario o contraseña incorrectos" para todos los casos).
            $this->error = 'Email o contraseña incorrectos.';
            return;
        }

        // Login OK: resetear contador y limpiar bloqueo si lo había.
        Auth::user()->loginExitoso();

        session()->regenerate();
        session()->forget('colas');

        $this->redirect('/declarar-colas', navigate: false);
    }

    public function render()
    {
        return view('livewire.login')
            ->layout('layouts.minimal');
    }
}
