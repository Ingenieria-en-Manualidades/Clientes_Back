<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\MetaUnidades;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class MetaUnidadesController extends Controller
{
    public function create(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'valor' => 'required|integer',
                'fecha_meta' => 'required|date',
                'cliente_endpoint_id' => 'required|integer',
                'usuario' => 'required|string',
            ]);

            $clienteID = Cliente::where('cliente_endpoint_id', $validatedData['cliente_endpoint_id'])->first();

            if ($clienteID) {
                $objMetaUnidades = new MetaUnidades();
                $objMetaUnidades->valor = $validatedData['valor'];
                $objMetaUnidades->fecha_meta = $validatedData['fecha_meta'];
                $objMetaUnidades->clientes_id = $clienteID->id;
                $objMetaUnidades->usuario = $validatedData['usuario'];
                $objMetaUnidades->save();
                return response()->json(['title' => 'Guardado con exito.', 'message' => 'Unidades programadas guardadas con exito.'], 200);
            } else {
                return response()->json(['title' => 'Error al guardar.', 'message' => 'Cliente no encontrado en la BD.'], 404);
            }
        } catch (ValidationException $e) {
            return response()->json(['title' => 'Error de validación.', 'message' => 'Error en las unidades mensuales ingresadas.', 'error' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return response()->json(['title' => 'Error con el servidor.', 'message' => 'Ha ocurrido un fallo con el servidor.', 'error' => $e->getMessage()], 500);
        }
    }
}
