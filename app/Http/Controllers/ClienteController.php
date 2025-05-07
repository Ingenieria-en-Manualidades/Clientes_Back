<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ClienteController extends Controller
{
    public function getClientsByIds($arrayIds)
    {
        try {
            $ids = explode(',', $arrayIds);

            $clients = Cliente::select('cliente_endpoint_id', 'nombre')
            ->whereIn('cliente_endpoint_id', $ids)
            ->orderBy('nombre','asc')
            ->get();

            if ($clients->isEmpty()) {
                return response()->json(['title' => 'Clientes no encontrados.', 'message' => 'Usuario no relacionado con ningun cliente.'], 404);
            } else {
                return response()->json(['data' => $clients], 200);
            }
        } catch (\Exception $e) {
            return response()->json(['title' => 'Error con el servidor.', 'message' => 'Por favor recargar la página.', 'error' => $e->getMessage()], 500);
        }
    }
}
