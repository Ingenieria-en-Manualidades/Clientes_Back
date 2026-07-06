<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class ClienteUserController extends Controller
{
    public function getClientsByUserId ()
    {
        try {
            $user = Auth::user();
            Log::info("message", ['user' => $user]);

            if ($user) {
                $clients = DB::table('cliente_user as cu')
                ->join('clientes as c', 'cu.cliente_id', '=', 'c.id')
                ->select('c.cliente_endpoint_id', 'c.nombre')
                ->where('cu.user_id', '=', $user->id)
                ->whereNull('cu.deleted_at')
                ->whereNull('c.deleted_at')
                ->orderBy('c.nombre', 'asc')
                ->get();

                if ($clients->isEmpty()) {
                    return response()->json(['title' => 'Clientes sin relacionar.', 'message' => 'Usuario no relacionado con ningun cliente.'], 404);
                } else {
                    return response()->json(['data' => $clients], 200);
                }
            } else {
                return response()->json(['title' => 'Usuario inexistente.', 'message' => 'El usuario no existe en el sistema.'], 404);
            }
        } catch (\Exception $e) {
            return response()->json(['title' => 'Error con el servidor.', 'message' => 'Por favor recargar la página.', 'error' => $e->getMessage()], 500);
        }
    }
}
