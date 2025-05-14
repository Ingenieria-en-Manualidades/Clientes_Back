<?php

namespace App\Http\Controllers;

use DateTime;
use App\Models\Cliente;
use App\Models\MetaUnidades;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
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
                'area_id' => 'required|integer',
                'usuario' => 'required|string',
            ]);

            $clienteID = Cliente::where('cliente_endpoint_id', $validatedData['cliente_endpoint_id'])->first();
            if ($clienteID) {
                $dateMeta = new DateTime($validatedData['fecha_meta']);

                $metaExist = DB::table('meta_unidades as mu')
                ->join('clientes as c', 'mu.clientes_id', '=', 'c.id')
                ->where('mu.fecha_meta', 'like', $dateMeta->format('Y') .'-'. $dateMeta->format('m') .'%')
                ->where('c.cliente_endpoint_id', '=', $validatedData['cliente_endpoint_id'])
                ->where('mu.area_id_groot', '=', $validatedData['area_id'])
                ->whereNull('mu.deleted_at')
                ->first();
                
                if ($metaExist) {
                    return response()->json(['title' => 'Unidades existentes.', 'message' => 'Existen unidades programadas para el mes y area ingresados.', 'data' => $metaExist], 409);
                } else {
                    $objMetaUnidades = new MetaUnidades();
                    $objMetaUnidades->valor = $validatedData['valor'];
                    $objMetaUnidades->fecha_meta = $validatedData['fecha_meta'];
                    $objMetaUnidades->actualizaciones = 1;
                    $objMetaUnidades->clientes_id = $clienteID->id;
                    $objMetaUnidades->usuario = $validatedData['usuario'];
                    $objMetaUnidades->area_id_groot = $validatedData['area_id'];
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

    public function list(Request $request) {
        try {
            $validatedData = $request->validate([
                'arraysAreas' => 'required|array',
                'cliente_endpoint_id' => 'required|integer',
            ]);
            $arraysAreas = $validatedData['arraysAreas'];

            $client = Cliente::where('cliente_endpoint_id', $validatedData['cliente_endpoint_id'])->first();

            if ($client) {
                $areasIds = array_column($arraysAreas, 'area_id');
                $data = MetaUnidades::select(
                    'meta_unidades_id',
                    'valor',
                    'fecha_meta',
                    'updated_at',
                    'area_id_groot',
                    'usuario',
                )->where('clientes_id', $client->id)
                ->orderBy('fecha_meta', 'desc')
                ->get()
                ->map(function ($item) use ($areasIds, $arraysAreas) {
                    if (in_array($item->area_id_groot, $areasIds)) {
                        foreach ($arraysAreas as $area) {
                            if ($item->area_id_groot === $area['area_id']) {
                                $item->area_id_groot = $area['nombre_area'];
                            }
                        }
                    }
                    return $item;
                });
                return response()->json(['data' => $data], 200);
            } else {
                return response()->json(['title' => 'Error al guardar.', 'message' => 'Cliente no encontrado en la BD.'], 404);
            }
        } catch (ValidationException $e) {
            return response()->json(['title' => 'Error de validación.', 'message' => 'Error en las unidades mensuales ingresadas.', 'error' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return response()->json(['title' => 'Error con el servidor.', 'message' => 'Ha ocurrido un fallo con el servidor.', 'error' => $e->getMessage()], 500);
        }
    }

    public function getMetaUnidades($meta_unidades_id) {
        try {
            $metaUnidades = MetaUnidades::select('fecha_meta','valor')
            ->where('meta_unidades_id', $meta_unidades_id)
            ->first();

            if ($metaUnidades) {
                return response()->json(['meta_unidades' => $metaUnidades], 200);
            } else {
                return response()->json(['title' => 'Error en la meta.', 'message' => 'Meta no encontrada en la BD.'], 404);
            }
        } catch (\Exception $e) {
            return response()->json(['title' => 'Error con el servidor.', 'message' => 'Ha ocurrido un fallo con el servidor.', 'error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request) {
        try {
            $validatedData = $request->validate([
                'valor' => 'required|integer',
                'usuario' => 'required|string',
                'meta_unidades_id' => 'required|string',
                'motivo_actualizacion' => 'required|string',
            ]);

            $metaUnidades = MetaUnidades::where('meta_unidades_id', $validatedData['meta_unidades_id'])->first();

            if ($metaUnidades) {
                Log::info("message", ['metaUnidades' => $metaUnidades]);
                // $newMetaUnidades = new MetaUnidades();
                // $newMetaUnidades->valor = $validatedData['valor'];
                // $newMetaUnidades->fecha_meta = ;
                // $newMetaUnidades->actualizaciones = 1;
                // $newMetaUnidades->clientes_id = ;
                // $newMetaUnidades->usuario = $validatedData['usuario'];
                // $newMetaUnidades->area_id_groot = ;
                // $newMetaUnidades->save();
                return response()->json(['meta_unidades' => "exito"], 200);
            } else {
                return response()->json(['title' => 'Error en la meta.', 'message' => 'Meta no encontrada en la BD.'], 404);
            }

        } catch (ValidationException $e) {
            return response()->json(['title' => 'Error de validación.', 'message' => 'Error en las unidades mensuales ingresadas.', 'error' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return response()->json(['title' => 'Error con el servidor.', 'message' => 'Ha ocurrido un fallo con el servidor.', 'error' => $e->getMessage()], 500);
        }
    }

    public function getAreasImec($clienteID) {
        $response = Http::withoutVerifying()->get("https://imecplusdev.ienm.com.co:8443/api/area/listarCliente/{$clienteID}");

        if ($response->successful()) {
            $post = $response->json();
            return response()->json(['data' => $post], 200);
        } else {
            return response()->json(['error' => 'No se pudo :-('], 400);
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
