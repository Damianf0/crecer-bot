<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UsuariosController extends Controller
{
    public function index(): JsonResponse
    {
        $usuarios = User::select('id', 'nombre_completo', 'email', 'rol', 'activo', 'permisos', 'created_at')
            ->orderBy('nombre_completo')
            ->get()
            ->map(function ($u) {
                // Exponer permisos efectivos (con fallback a default del rol)
                $u->permisos_efectivos = $u->permisosEfectivos();
                return $u;
            });

        return response()->json([
            'ok'   => true,
            'data' => $usuarios,
            'meta' => [
                'permisos_disponibles' => User::PERMISOS_LABELS,
                'permisos_default'     => User::PERMISOS_DEFAULT,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nombre_completo' => 'required|string|max:100',
            'email'           => 'required|email|unique:users,email',
            'password'        => 'required|string|min:8',
            'rol'             => 'required|in:secretaria,supervisora,admin,tecnico',
        ]);

        $user = User::create([
            'name'            => $data['nombre_completo'],
            'nombre_completo' => $data['nombre_completo'],
            'email'           => $data['email'],
            'password'        => Hash::make($data['password']),
            'rol'             => $data['rol'],
            'activo'          => true,
            // permisos null → tomará los default del rol automáticamente
        ]);

        return response()->json(['ok' => true, 'id' => $user->id], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $data = $request->validate([
            'nombre_completo' => 'sometimes|string|max:100',
            'rol'             => 'sometimes|in:secretaria,supervisora,admin,tecnico',
            'activo'          => 'sometimes|boolean',
            'password'        => 'sometimes|string|min:8',
            'permisos'        => 'sometimes|array',
            'permisos.*'      => 'string|in:secretaria,atencion,contactos,agenda,historial,admin',
        ]);

        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        if (isset($data['nombre_completo'])) {
            $data['name'] = $data['nombre_completo'];
        }

        $user->update($data);

        return response()->json(['ok' => true]);
    }
}
