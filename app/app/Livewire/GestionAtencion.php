<?php

namespace App\Livewire;

use App\Models\ConversacionEvento;
use App\Models\ConversacionWA;
use App\Models\Derivacion;
use App\Models\MensajeWA;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Component;
use Livewire\WithFileUploads;

class GestionAtencion extends Component
{
    use WithFileUploads;
    // Filtros
    public string $filtro = 'todos'; // todos | bot | wa

    // Item seleccionado (para chat WA inline)
    public ?int $convAbiertaId = null;

    // Input chat
    public string $textoChat = '';
    public string $modoChat  = 'mensaje'; // mensaje | nota
    public $archivoChat = null;

    // Delegar modal
    public bool   $mostrarDelegar  = false;
    public ?int   $delegarId       = null;
    public string $delegarTipo     = '';
    public ?int   $delegarUsuario  = null;

    // Derivar a otra área (otro número de WhatsApp)
    public bool   $mostrarDerivarArea = false;
    public ?int   $derivarAreaConvId  = null;
    public string $derivarAreaDestino = '';

    // Toast
    public string $toast    = '';
    public string $toastTipo = 'ok';

    // ── Render ─────────────────────────────────────────────

    public function render()
    {
        $nuevasDerivaciones  = collect();
        $nuevasWA            = collect();
        $procesoDerivaciones = collect();
        $procesoWA           = collect();

        if (in_array($this->filtro, ['todos', 'bot'])) {
            $nuevasDerivaciones = Derivacion::where('estado', 'pendiente')
                ->whereNull('asignada_a')
                ->with('asignadaA')
                ->orderByDesc('urgente')
                ->orderBy('bot_at')
                ->get();

            $procesoDerivaciones = Derivacion::where('estado', 'en_atencion')
                ->with('asignadaA')
                ->orderByDesc('urgente')
                ->orderBy('bot_at')
                ->get();
        }

        if (in_array($this->filtro, ['todos', 'wa'])) {
            $areasSesion = ConversacionWA::areasDeLaSesion();

            $nuevasWA = ConversacionWA::where('estado', 'activa')
                ->whereIn('area', $areasSesion)
                ->whereNull('asignada_a')
                ->with(['ultimoMensaje', 'asignadaA'])
                ->orderByDesc('urgente')
                ->orderByDesc('ultima_actividad')
                ->get();

            $procesoWA = ConversacionWA::where('estado', 'activa')
                ->whereIn('area', $areasSesion)
                ->whereNotNull('asignada_a')
                ->with(['ultimoMensaje', 'asignadaA'])
                ->orderByDesc('urgente')
                ->orderByDesc('ultima_actividad')
                ->get();
        }

        // Merge y ordenar: urgentes primero, después por tiempo
        $nuevas = $nuevasDerivaciones->map(fn($d) => $this->mapDerivacion($d))
            ->concat($nuevasWA->map(fn($c) => $this->mapWA($c)))
            ->sortByDesc('urgente')
            ->sortByDesc('urgente') // mantener urgentes arriba
            ->values();

        // Ordenar: urgentes primero dentro de cada grupo
        $nuevas = $nuevas->sortByDesc(fn($i) => [$i['urgente'] ? 1 : 0, -strtotime($i['tiempo_raw'])])->values();

        $enProceso = $procesoDerivaciones->map(fn($d) => $this->mapDerivacion($d))
            ->concat($procesoWA->map(fn($c) => $this->mapWA($c)))
            ->sortByDesc(fn($i) => [$i['urgente'] ? 1 : 0])
            ->values();

        // Conversación abierta
        $convAbierta     = null;
        $mensajesAbierta = collect();
        if ($this->convAbiertaId) {
            $convAbierta     = ConversacionWA::with('tareas')->find($this->convAbiertaId);
            $mensajesAbierta = MensajeWA::where('conversacion_id', $this->convAbiertaId)
                ->orderBy('created_at')
                ->get();
        }

        $usuarios = User::where('activo', true)->orderBy('nombre_completo')->get();

        return view('livewire.gestion-atencion', compact(
            'nuevas', 'enProceso', 'convAbierta', 'mensajesAbierta', 'usuarios'
        ))->layout('layouts.app', ['title' => 'Atención']);
    }

    // ── Mappers ────────────────────────────────────────────

    private function mapDerivacion(Derivacion $d): array
    {
        return [
            'id'         => $d->id,
            'tipo'       => 'bot',
            'contacto'   => $d->telefono,
            'etiqueta'   => $d->etiqueta,
            'resumen'    => $d->resumen_llm ?: \Illuminate\Support\Str::limit($d->texto, 120),
            'texto_full' => $d->texto,
            'urgente'    => $d->urgente,
            'asignada_a' => $d->asignadaA?->nombre_completo,
            'asig_id'    => $d->asignada_a,
            'hace'       => $d->bot_at?->diffForHumans(),
            'tiempo_raw' => $d->bot_at?->toDateTimeString(),
            'es_prueba'  => $d->es_prueba,
            'estado'     => $d->estado,
            'codigo'     => $d->codigo,
        ];
    }

    private function mapWA(ConversacionWA $c): array
    {
        $ultimo = $c->ultimoMensaje;
        return [
            'id'         => $c->id,
            'tipo'       => 'wa',
            'contacto'   => $c->nombreOTelefono,
            'telefono'   => $c->telefono,
            'etiqueta'   => 'WhatsApp',
            'resumen'    => $c->resumen_llm ?: ($ultimo?->snippet ?? '—'),
            'urgente'    => $c->urgente,
            'asignada_a' => $c->asignadaA?->nombre_completo,
            'asig_id'    => $c->asignada_a,
            'hace'       => $c->ultima_actividad?->diffForHumans(),
            'tiempo_raw' => $c->ultima_actividad?->toDateTimeString(),
            'no_leidos'  => $c->no_leidos,
            'estado'     => $c->estado,
        ];
    }

    // ── Acciones ───────────────────────────────────────────

    public function tomar(int $id, string $tipo): void
    {
        $userId = Auth::id();

        if ($tipo === 'bot') {
            $item = Derivacion::findOrFail($id);
            $item->update(['asignada_a' => $userId, 'estado' => 'en_atencion']);
            $this->generarResumenSiNecesario($item, $tipo);
        } else {
            $item = ConversacionWA::findOrFail($id);
            $item->update(['asignada_a' => $userId]);
            $this->generarResumenSiNecesario($item, $tipo);
            $this->convAbiertaId = $id;
        }

        $this->toast    = 'Tomado';
        $this->toastTipo = 'ok';
        $this->dispatch('toast');
    }

    public function abrirDelegar(int $id, string $tipo): void
    {
        $this->delegarId      = $id;
        $this->delegarTipo    = $tipo;
        $this->delegarUsuario = null;
        $this->mostrarDelegar = true;
    }

    public function confirmarDelegar(): void
    {
        if (!$this->delegarUsuario) return;

        if ($this->delegarTipo === 'bot') {
            $item = Derivacion::findOrFail($this->delegarId);
            $item->update(['asignada_a' => $this->delegarUsuario, 'estado' => 'en_atencion']);
        } else {
            $item = ConversacionWA::findOrFail($this->delegarId);
            $item->update(['asignada_a' => $this->delegarUsuario]);
        }

        $this->mostrarDelegar = false;
        $this->toast    = 'Delegado';
        $this->toastTipo = 'ok';
        $this->dispatch('toast');
    }

    public function cancelarDelegar(): void
    {
        $this->mostrarDelegar = false;
    }

    // ── Derivar a otra área (otro número de WhatsApp) ──────

    public function abrirDerivarArea(int $convId): void
    {
        $this->derivarAreaConvId  = $convId;
        $this->derivarAreaDestino = '';
        $this->mostrarDerivarArea = true;
    }

    public function cancelarDerivarArea(): void
    {
        $this->mostrarDerivarArea = false;
    }

    public function confirmarDerivarArea(): void
    {
        $conv = ConversacionWA::find($this->derivarAreaConvId);
        if (!$conv) { $this->mostrarDerivarArea = false; return; }

        $destino = $this->derivarAreaDestino;
        if (!isset(ConversacionWA::AREAS[$destino]) || $destino === $conv->area) {
            $this->toast = 'Elegí un área distinta a la actual.';
            $this->toastTipo = 'error';
            $this->dispatch('toast');
            return;
        }

        $origenLabel  = ConversacionWA::AREAS[$conv->area] ?? $conv->area;
        $destinoLabel = ConversacionWA::AREAS[$destino];
        $botTok       = config('app.bot_ingress_token');

        // Teléfono del bot destino, en vivo. Si no responde, mensaje genérico sin número.
        $telDestino = null;
        try {
            $r = Http::timeout(8)->withToken($botTok)->get(ConversacionWA::botUrlPara($destino) . '/status');
            if ($r->ok() && $r->json('phone')) $telDestino = $r->json('phone');
        } catch (\Throwable) {}

        $texto = $telDestino
            ? "Hola 👋 Tu consulta corresponde al área de *{$destinoLabel}*. Te derivamos con ese equipo — te van a responder desde el número +{$telDestino}. ¡Gracias!"
            : "Hola 👋 Tu consulta corresponde al área de *{$destinoLabel}*. Te derivamos con ese equipo y te van a responder a la brevedad. ¡Gracias!";

        // Avisar al paciente POR EL NÚMERO ACTUAL. Si esto falla, no movemos nada.
        $waId = null;
        try {
            $resp = Http::timeout(12)->withToken($botTok)->post($conv->botUrl() . '/enviar', [
                'contacto' => $conv->contacto,
                'texto'    => $texto,
            ]);
            if (!$resp->ok() || $resp->json('ok') !== true) {
                $this->toast = "No se pudo avisar al paciente (el bot de {$origenLabel} no respondió). No se derivó.";
                $this->toastTipo = 'error';
                $this->dispatch('toast');
                return;
            }
            $waId = $resp->json('wa_id');
        } catch (\Throwable) {
            $this->toast = "No se pudo contactar al bot de {$origenLabel}. No se derivó.";
            $this->toastTipo = 'error';
            $this->dispatch('toast');
            return;
        }

        // Registrar el aviso como saliente (la conv todavía está en su área original).
        MensajeWA::create([
            'conversacion_id' => $conv->id,
            'direccion'       => 'saliente',
            'tipo'            => 'texto',
            'contenido'       => $texto,
            'wa_id'           => $waId,
            'usuario_id'      => Auth::id(),
            'leido'           => true,
        ]);

        // Mover al área destino. Si ya existe una conv (contacto, destino), fusionar.
        $existente = ConversacionWA::where('contacto', $conv->contacto)
            ->where('area', $destino)
            ->where('id', '!=', $conv->id)
            ->first();

        if ($existente) {
            MensajeWA::where('conversacion_id', $conv->id)->update(['conversacion_id' => $existente->id]);
            \App\Models\TareaWA::where('conversacion_id', $conv->id)->update(['conversacion_id' => $existente->id]);
            ConversacionEvento::where('conversacion_id', $conv->id)->update(['conversacion_id' => $existente->id]);
            $existente->update([
                'estado'           => 'activa',
                'no_leidos'        => $existente->no_leidos + $conv->no_leidos,
                'ultima_actividad' => now(),
                'asignada_a'       => null,
            ]);
            $conv->delete();
            $destinoConvId = $existente->id;
        } else {
            $conv->update([
                'area'             => $destino,
                'asignada_a'       => null,
                'estado'           => 'activa',
                'ultima_actividad' => now(),
            ]);
            $destinoConvId = $conv->id;
        }

        ConversacionEvento::create([
            'conversacion_id' => $destinoConvId,
            'tipo'            => 'derivada_area',
            'usuario_id'      => Auth::id(),
        ]);

        Cache::forget('atencion.items');

        $this->mostrarDerivarArea = false;
        $this->convAbiertaId      = null;
        $this->toast    = "Derivada a {$destinoLabel} ✓";
        $this->toastTipo = 'ok';
        $this->dispatch('toast');
    }

    public function toggleUrgente(int $id, string $tipo): void
    {
        if ($tipo === 'bot') {
            $item = Derivacion::findOrFail($id);
        } else {
            $item = ConversacionWA::findOrFail($id);
        }
        $item->update(['urgente' => !$item->urgente]);
    }

    public function resolver(int $id, string $tipo): void
    {
        if ($tipo === 'bot') {
            Derivacion::findOrFail($id)->update(['estado' => 'resuelto', 'atendido_at' => now()]);
        } else {
            $conv = ConversacionWA::findOrFail($id);
            $conv->update(['estado' => 'archivada', 'asignada_a' => null, 'urgente' => false]);
            if ($this->convAbiertaId === $id) {
                $this->convAbiertaId = null;
            }
        }
        $this->toast    = 'Resuelto';
        $this->toastTipo = 'ok';
        $this->dispatch('toast');
    }

    public function abrirConv(int $id): void
    {
        $this->convAbiertaId = ($this->convAbiertaId === $id) ? null : $id;
        $this->textoChat = '';

        if ($this->convAbiertaId) {
            // Marcar leídos
            ConversacionWA::where('id', $id)->update(['no_leidos' => 0]);
            MensajeWA::where('conversacion_id', $id)->update(['leido' => true]);
            $this->dispatch('scroll-bottom');
        }
    }

    public function cerrarConv(): void
    {
        $this->convAbiertaId = null;
    }

    public function enviarMensaje(): void
    {
        if (!$this->convAbiertaId || trim($this->textoChat) === '') return;

        $conv = ConversacionWA::findOrFail($this->convAbiertaId);
        $texto = trim($this->textoChat);

        if ($this->modoChat === 'nota') {
            MensajeWA::create([
                'conversacion_id' => $conv->id,
                'direccion'       => 'nota_interna',
                'tipo'            => 'texto',
                'contenido'       => $texto,
                'usuario_id'      => Auth::id(),
                'leido'           => true,
            ]);
        } else {
            // Enviar por WhatsApp — bot del área de la conversación
            $botUrl = $conv->botUrl();
            $botTok = config('app.bot_ingress_token');
            try {
                Http::timeout(10)
                    ->withToken($botTok)
                    ->post("{$botUrl}/enviar", [
                        'contacto' => $conv->contacto,
                        'texto'    => $texto,
                    ]);
            } catch (\Exception $e) {
                // No bloquear si el bot no responde
            }

            MensajeWA::create([
                'conversacion_id' => $conv->id,
                'direccion'       => 'saliente',
                'tipo'            => 'texto',
                'contenido'       => $texto,
                'usuario_id'      => Auth::id(),
                'leido'           => true,
            ]);
            $conv->update(['ultima_actividad' => now()]);
        }

        $this->textoChat = '';
        $this->dispatch('scroll-bottom');
    }

    public function enviarArchivo(): void
    {
        if (!$this->convAbiertaId || !$this->archivoChat) return;

        $conv      = ConversacionWA::findOrFail($this->convAbiertaId);
        $archivo   = $this->archivoChat;
        $mimetype  = $archivo->getMimeType();
        $filename  = $archivo->getClientOriginalName();

        // Whitelist de mimetype + extensión
        if (!in_array($mimetype, \App\Http\Controllers\AtencionController::mimetypesPermitidos(), true)) {
            $this->toast('Tipo de archivo no permitido', 'error');
            return;
        }
        if ($ext = \App\Http\Controllers\AtencionController::extensionBloqueada($filename)) {
            $this->toast("Extensión .{$ext} no permitida", 'error');
            return;
        }

        $base64    = base64_encode(file_get_contents($archivo->getRealPath()));
        $caption   = trim($this->textoChat);

        // Determinar tipo para el registro
        $tipo = match(true) {
            str_starts_with($mimetype, 'image/') => 'imagen',
            str_starts_with($mimetype, 'video/') => 'video',
            str_starts_with($mimetype, 'audio/') => 'audio',
            default                              => 'documento',
        };

        // Guardar en media local y generar URL pública
        $ext         = $archivo->getClientOriginalExtension();
        $localName   = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        $mediaPath   = storage_path('app/public/wa-media');
        if (!is_dir($mediaPath)) mkdir($mediaPath, 0755, true);
        $archivo->storeAs('public/wa-media', $localName);
        $archivoUrl  = asset("storage/wa-media/{$localName}");

        // Enviar por WhatsApp vía bot del área de la conversación
        $botUrl = $conv->botUrl();
        $botTok = config('app.bot_ingress_token');
        try {
            Http::timeout(30)
                ->withToken($botTok)
                ->post("{$botUrl}/enviar-archivo", [
                    'contacto' => $conv->contacto,
                    'base64'   => $base64,
                    'mimetype' => $mimetype,
                    'filename' => $filename,
                    'caption'  => $caption,
                ]);
        } catch (\Exception $e) {
            // No bloquear si el bot no responde
        }

        MensajeWA::create([
            'conversacion_id' => $conv->id,
            'direccion'       => 'saliente',
            'tipo'            => $tipo,
            'contenido'       => $caption ?: $filename,
            'archivo_url'     => $archivoUrl,
            'usuario_id'      => Auth::id(),
            'leido'           => true,
        ]);
        $conv->update(['ultima_actividad' => now()]);

        $this->archivoChat = null;
        $this->textoChat   = '';
        $this->dispatch('scroll-bottom');
        $this->dispatch('limpiar-archivo');
    }

    // ── Helpers ────────────────────────────────────────────

    private function generarResumenSiNecesario($item, string $tipo): void
    {
        if ($item->resumen_llm) return;

        try {
            if ($tipo === 'bot') {
                $texto = $item->texto;
            } else {
                $mensajes = MensajeWA::where('conversacion_id', $item->id)
                    ->whereIn('direccion', ['entrante', 'saliente'])
                    ->orderBy('created_at')
                    ->take(20)
                    ->get();
                $texto = $mensajes->map(fn($m) =>
                    ($m->direccion === 'entrante' ? 'Paciente: ' : 'Bot: ') . ($m->contenido ?? '[audio]')
                )->implode("\n");
            }

            if (!trim($texto)) return;

            $botUrl = config('app.bot_url');
            $botTok = config('app.bot_ingress_token');
            $resp   = Http::timeout(15)
                ->withToken($botTok)
                ->post("{$botUrl}/resumir", ['texto' => $texto]);
            if ($resp->ok() && $resp->json('resumen')) {
                $item->update(['resumen_llm' => $resp->json('resumen')]);
            }
        } catch (\Exception $e) {
            // Silencioso — el resumen es opcional
        }
    }
}
