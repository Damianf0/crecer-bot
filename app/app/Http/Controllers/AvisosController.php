<?php

namespace App\Http\Controllers;

use App\Models\PreferenciaAviso;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** "Mis avisos": qué avisos del panel recibe cada persona y si suenan. */
class AvisosController extends Controller
{
    /** GET /mis-avisos */
    public function show(): JsonResponse
    {
        return response()->json([
            'ok'        => true,
            'prefs'     => PreferenciaAviso::para((int) Auth::id()),
            'etiquetas' => PreferenciaAviso::ETIQUETAS,
        ]);
    }

    /** POST /mis-avisos — solo las claves conocidas, todas booleanas. */
    public function update(Request $request): JsonResponse
    {
        $reglas = array_fill_keys(array_keys(PreferenciaAviso::DEFAULTS), 'sometimes|boolean');
        $datos = $request->validate($reglas);

        $uid = (int) Auth::id();
        $prefs = array_merge(PreferenciaAviso::para($uid), array_map('boolval', $datos));
        PreferenciaAviso::updateOrCreate(['user_id' => $uid], ['prefs' => $prefs]);

        return response()->json(['ok' => true, 'prefs' => PreferenciaAviso::para($uid)]);
    }
}
