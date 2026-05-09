<div>
<div class="card">
    <div class="card-title">Iniciar sesión</div>

    @if ($error)
        <div class="error-msg">{{ $error }}</div>
    @endif

    <div class="form-group">
        <label>Email</label>
        <input type="email" wire:model="email" wire:keydown.enter="intentarLogin"
               placeholder="tu@email.com" autocomplete="email">
    </div>

    <div class="form-group">
        <label>Contraseña</label>
        <input type="password" wire:model="password" wire:keydown.enter="intentarLogin"
               placeholder="••••••••" autocomplete="current-password">
    </div>

    <button class="btn" wire:click="intentarLogin" wire:loading.attr="disabled">
        <span wire:loading.remove>Ingresar →</span>
        <span wire:loading>Verificando...</span>
    </button>
</div>
</div>
