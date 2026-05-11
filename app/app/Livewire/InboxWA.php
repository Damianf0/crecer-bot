<?php

namespace App\Livewire;

use App\Models\Contacto;
use App\Models\ConversacionWA;
use App\Models\MensajeWA;
use App\Models\TareaWA;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Livewire\Component;

class InboxWA extends Component
{
    public ?int  $convId       = null;
    public string $texto       = '';
    public string $modo        = 'mensaje';   // mensaje | nota
    public string $buscar      = '';
    public string $filtro      = 'activa';    // activa | archivada
    public bool   $mostrarTareas = true;
    public string $toastMsg    = '';
    public string $toastType   = '';

    // Form nueva tarea
    public bool   $formTarea   = false;
    public string $tituloTarea = '';
    public string $descTarea   = '';
    public string $venceAt     = '';
    public ?int   $asignadoA   = null;

    // Editar nombre contacto
    public bool   $editandoNombre = false;
    public string $nombreEditar   = '';

    public function getConversacionesProperty()
    {
        return ConversacionWA::where('estado', $this->filtro)
            ->whereIn('area', ConversacionWA::areasDeLaSesion())
            ->with(['ultimoMensaje'])
            ->when($this->buscar, fn($q) => $q->where(function ($q) {
                $q->where('contacto', 'like', "%{$this->buscar}%")
                  ->orWhere('nombre',   'like', "%{$this->buscar}%");
            }))
            ->orderBy('ultima_actividad', 'desc')
            ->get();
    }

    public function getConvActivaProperty(): ?ConversacionWA
    {
        return $this->convId ? ConversacionWA::find($this->convId) : null;
    }

    public function getMensajesProperty()
    {
        if (!$this->convId) return collect();
        return MensajeWA::where('conversacion_id', $this->convId)
            ->with('usuario')
            ->orderBy('created_at')
            ->get();
    }

    public function getTareasProperty()
    {
        if (!$this->convId) return collect();
        return TareaWA::where('conversacion_id', $this->convId)
            ->with(['asignadoA', 'creadoPor'])
            ->orderByRaw("FIELD(estado, 'pendiente', 'completada')")
            ->orderBy('vence_at')
            ->orderBy('created_at')
            ->get();
    }

    public function getUsuariosProperty()
    {
        return User::orderBy('nombre_completo')->get(['id', 'nombre_completo']);
    }

    public function seleccionar(int $id): void
    {
        $this->convId       = $id;
        $this->texto        = '';
        $this->modo         = 'mensaje';
        $this->formTarea    = false;
        $this->editandoNombre = false;

        // Marcar como leídos
        MensajeWA::where('conversacion_id', $id)->where('leido', false)->update(['leido' => true]);
        ConversacionWA::where('id', $id)->update(['no_leidos' => 0]);

        $this->dispatch('conversacion-abierta');
    }

    public function enviar(): void
    {
        $texto = trim($this->texto);
        if (!$this->convId || !$texto) return;

        $conv = ConversacionWA::find($this->convId);
        if (!$conv) return;

        if ($this->modo === 'nota') {
            MensajeWA::create([
                'conversacion_id' => $conv->id,
                'direccion'       => 'nota_interna',
                'tipo'            => 'texto',
                'contenido'       => $texto,
                'usuario_id'      => auth()->id(),
                'leido'           => true,
            ]);
            $this->texto = '';
            return;
        }

        // Mensaje saliente — llamar al bot del área de la conversación
        $botUrl = $conv->botUrl();
        $botTok = config('app.bot_ingress_token');
        try {
            $res = Http::timeout(10)
                ->withToken($botTok)
                ->post("{$botUrl}/enviar", [
                    'contacto' => $conv->contacto,
                    'texto'    => $texto,
                ]);
            if (!$res->successful()) {
                $this->toast('Error al enviar: ' . $res->body(), 'error');
                return;
            }
        } catch (\Exception $e) {
            $this->toast('Bot no disponible: ' . $e->getMessage(), 'error');
            return;
        }

        MensajeWA::create([
            'conversacion_id' => $conv->id,
            'direccion'       => 'saliente',
            'tipo'            => 'texto',
            'contenido'       => $texto,
            'usuario_id'      => auth()->id(),
            'leido'           => true,
        ]);

        $conv->update(['ultima_actividad' => now()]);
        $this->texto = '';
        $this->dispatch('conversacion-abierta');
    }

    public function crearTarea(): void
    {
        $titulo = trim($this->tituloTarea);
        if (!$this->convId || !$titulo) return;

        TareaWA::create([
            'conversacion_id' => $this->convId,
            'titulo'          => $titulo,
            'descripcion'     => trim($this->descTarea) ?: null,
            'estado'          => 'pendiente',
            'asignado_a'      => $this->asignadoA ?: null,
            'creado_por'      => auth()->id(),
            'vence_at'        => $this->venceAt ?: null,
        ]);

        $this->tituloTarea = '';
        $this->descTarea   = '';
        $this->venceAt     = '';
        $this->asignadoA   = null;
        $this->formTarea   = false;
        $this->toast('Tarea creada', 'ok');
    }

    public function toggleTarea(int $id): void
    {
        $tarea = TareaWA::find($id);
        if (!$tarea) return;

        if ($tarea->estado === 'pendiente') {
            $tarea->update(['estado' => 'completada', 'completado_at' => now()]);
        } else {
            $tarea->update(['estado' => 'pendiente', 'completado_at' => null]);
        }
    }

    public function eliminarTarea(int $id): void
    {
        TareaWA::find($id)?->delete();
    }

    public function archivar(int $id): void
    {
        ConversacionWA::find($id)?->update(['estado' => 'archivada']);
        if ($this->convId === $id) $this->convId = null;
        $this->toast('Conversación archivada', 'ok');
    }

    public function desarchivar(int $id): void
    {
        ConversacionWA::find($id)?->update(['estado' => 'activa']);
        $this->toast('Conversación restaurada', 'ok');
    }

    public function editarNombre(): void
    {
        $conv = ConversacionWA::find($this->convId);
        if (!$conv) return;
        $nombre = trim($this->nombreEditar) ?: null;
        $conv->update(['nombre' => $nombre]);
        // Guardar en directorio para próximas conversaciones del mismo número
        if ($nombre) {
            Contacto::guardar($conv->contacto, $nombre);
        }
        $this->editandoNombre = false;
        $this->toast('Nombre actualizado', 'ok');
    }

    public function abrirEditarNombre(): void
    {
        $this->nombreEditar   = $this->convActiva?->nombre ?? '';
        $this->editandoNombre = true;
    }

    private function toast(string $msg, string $type): void
    {
        $this->toastMsg  = $msg;
        $this->toastType = $type;
        $this->dispatch('toast');
    }

    public function render()
    {
        return view('livewire.inbox-wa', [
            'conversaciones' => $this->conversaciones,
            'convActiva'     => $this->convActiva,
            'mensajes'       => $this->mensajes,
            'tareas'         => $this->tareas,
            'usuarios'       => $this->usuarios,
        ])->layout('layouts.app', ['title' => 'Inbox WhatsApp']);
    }
}
