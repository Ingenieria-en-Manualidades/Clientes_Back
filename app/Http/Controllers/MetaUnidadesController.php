<?php

namespace App\Http\Controllers;

use DateTime;
use App\Models\Cliente;
use App\Models\MetaUnidades;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
                $dateMeta = new DateTime($validatedData['fecha_meta']);

                $metaExist = DB::table('meta_unidades as mu')
                ->join('clientes as c', 'mu.clientes_id', '=', 'c.id')
                ->where('mu.fecha_meta', 'like', $dateMeta->format('Y') .'-'. $dateMeta->format('m') .'%')
                ->where('c.cliente_endpoint_id', '=', $validatedData['cliente_endpoint_id'])
                ->whereNull('mu.deleted_at')
                ->first();
                
                if ($metaExist) {
                    return response()->json(['title' => 'Unidades existentes.', 'message' => 'Ya hay unidades programadas para el mes ingresado.', 'data' => $metaExist], 409);
                } else {
                    $objMetaUnidades = new MetaUnidades();
                    $objMetaUnidades->valor = $validatedData['valor'];
                    $objMetaUnidades->fecha_meta = $validatedData['fecha_meta'];
                    $objMetaUnidades->clientes_id = $clienteID->id;
                    $objMetaUnidades->usuario = $validatedData['usuario'];
                    $objMetaUnidades->save();
                    return response()->json(['title' => 'Guardado con exito.', 'message' => 'Unidades programadas guardadas con exito.'], 200);
                }
            } else {
                return response()->json(['title' => 'Error al guardar.', 'message' => 'Cliente no encontrado en la BD.'], 404);
            }
        } catch (ValidationException $e) {
            return response()->json(['title' => 'Error de validación.', 'message' => 'Error en las unidades mensuales ingresadas.', 'error' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return response()->json(['title' => 'Error con el servidor.', 'message' => 'Ha ocurrido un fallo con el servidor.', 'error' => $e->getMessage()], 500);
        }
    }

    public function list($client_endpoint_id) {
        try {
            $client = Cliente::where('cliente_endpoint_id', $client_endpoint_id)->first();

            if ($client) {
                $data = MetaUnidades::select(
                    'meta_unidades_id',
                    'valor',
                    'fecha_meta',
                    'updated_at',
                    'usuario',
                )->where('clientes_id', $client->id)
                ->get();
                return response()->json(['title' => 'Guardado con exito.', 'message' => 'Unidades programadas guardadas con exito.', 'data' => $data], 200);
            } else {
                return response()->json(['title' => 'Error al guardar.', 'message' => 'Cliente no encontrado en la BD.'], 404);
            }
        } catch (\Exception $e) {
            return response()->json(['title' => 'Error con el servidor.', 'message' => 'Ha ocurrido un fallo con el servidor.', 'error' => $e->getMessage()], 500);
        }
    }

    public function exists (Request $request)
    {
        try {
            $validatedData = $request->validate([
                'fecha_meta' => 'required|date',
                'cliente_endpoint_id' => 'required|integer',
            ]);

            $clienteID = Cliente::where('cliente_endpoint_id', $validatedData['cliente_endpoint_id'])->first();
            if ($clienteID) {
                $dateMeta = new DateTime($validatedData['fecha_meta']);

                $metaExist = DB::table('meta_unidades as mu')
                ->join('clientes as c', 'mu.clientes_id', '=', 'c.id')
                ->where('mu.fecha_meta', 'like', $dateMeta->format('Y') .'-'. $dateMeta->format('m') .'%')
                ->where('c.cliente_endpoint_id', '=', $validatedData['cliente_endpoint_id'])
                ->whereNull('mu.deleted_at')
                ->first();

                if ($metaExist) {
                    return response()->json(['exists' => true, 'title' => 'Unidades ya programadas.', 'message' => 'Ya hay unidades programadas para este mes.'], 200);
                } else {
                    return response()->json(['exists' => false, 'title' => '', 'message' => ''], 200);
                }
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
