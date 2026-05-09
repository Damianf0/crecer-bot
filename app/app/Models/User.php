<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'nombre_completo',
        'rol',
        'activo',
        'email',
        'password',
        'permisos',
        'intentos_fallidos',
        'bloqueado_hasta',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'activo'            => 'boolean',
            'permisos'          => 'array',
            'bloqueado_hasta'   => 'datetime',
        ];
    }

    /** Configuración de lockout */
    public const MAX_INTENTOS = 5;
    public const BLOQUEO_MINUTOS = 15;

    /** ¿El usuario está bloqueado por intentos fallidos? */
    public function estaBloqueado(): bool
    {
        return $this->bloqueado_hasta && $this->bloqueado_hasta->isFuture();
    }

    /** Minutos restantes de bloqueo (0 si no está bloqueado). */
    public function minutosBloqueoRestantes(): int
    {
        if (!$this->estaBloqueado()) return 0;
        return max(1, (int) ceil(now()->diffInSeconds($this->bloqueado_hasta, false) / 60));
    }

    /**
     * Registrar un intento fallido. Si llega al máximo, bloquea por BLOQUEO_MINUTOS.
     * Devuelve true si quedó bloqueado en este intento.
     */
    public function intentoFallido(): bool
    {
        $this->intentos_fallidos = ($this->intentos_fallidos ?? 0) + 1;
        if ($this->intentos_fallidos >= self::MAX_INTENTOS) {
            $this->bloqueado_hasta   = now()->addMinutes(self::BLOQUEO_MINUTOS);
            $this->intentos_fallidos = 0;  // resetea para próximo ciclo
            $this->save();
            return true;
        }
        $this->save();
        return false;
    }

    /** Resetear contador y bloqueo tras login exitoso. */
    public function loginExitoso(): void
    {
        if ($this->intentos_fallidos > 0 || $this->bloqueado_hasta) {
            $this->intentos_fallidos = 0;
            $this->bloqueado_hasta   = null;
            $this->save();
        }
    }

    public function sesiones()
    {
        return $this->hasMany(SesionSecretaria::class);
    }

    public function sesionActiva(): ?SesionSecretaria
    {
        return $this->sesiones()
            ->whereNull('fin_sesion')
            ->latest('inicio_sesion')
            ->first();
    }

    // Permisos por defecto según rol
    public const PERMISOS_DEFAULT = [
        'secretaria'  => ['secretaria', 'atencion', 'contactos'],
        'supervisora' => ['secretaria', 'atencion', 'contactos', 'agenda', 'historial', 'admin'],
        'admin'       => ['secretaria', 'atencion', 'contactos', 'agenda', 'historial', 'admin'],
        'tecnico'     => ['secretaria', 'atencion', 'contactos', 'agenda', 'historial', 'admin'],
    ];

    public const PERMISOS_LABELS = [
        'secretaria' => 'Cola de recepción',
        'atencion'   => 'Atención y mis tareas',
        'contactos'  => 'Contactos',
        'agenda'     => 'Agenda',
        'historial'  => 'Ver historial',
        'admin'      => 'Administración (panel)',
    ];

    // Devuelve los permisos efectivos: los guardados en DB, o los default del rol si nunca se configuraron
    public function permisosEfectivos(): array
    {
        if (!empty($this->permisos)) {
            return $this->permisos;
        }
        return self::PERMISOS_DEFAULT[$this->rol] ?? [];
    }

    public function hasPermiso(string $permiso): bool
    {
        return in_array($permiso, $this->permisosEfectivos(), true);
    }

    public const ROLES = [
        'secretaria'  => 'Secretaria',
        'supervisora' => 'Supervisora',
        'admin'       => 'Administrativo',
        'tecnico'     => 'Técnico WORKBENCH IT',
    ];

    public const COLAS = [
        'recepcion'    => 'Recepción general',
        'turnos'       => 'Turnos y agenda',
        'ordenes'      => 'Órdenes y resultados',
        'facturacion'  => 'Facturación y cobros',
        'coordinacion' => 'Coordinación médica',
    ];
}
