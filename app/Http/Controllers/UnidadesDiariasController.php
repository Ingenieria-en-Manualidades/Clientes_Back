<?php

namespace App\Http\Controllers;

use DateTime;
use App\Models\Cliente;
use App\Models\MetaUnidades;
use Illuminate\Http\Request;
use App\Models\UnidadesDiarias;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class UnidadesDiariasController extends Controller
{
    public function create(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'valor' => 'required|integer',
                'fecha_programacion' => 'required|date',
                'cliente_endpoint_id' => 'required|integer',
                'area_id' => 'required|integer',
                'usuario' => 'required|string',
            ]);
            $clienteID = Cliente::where('cliente_endpoint_id', $validatedData['cliente_endpoint_id'])->first();
            if ($clienteID) {
                $dateMeta = new DateTime($validatedData['fecha_programacion']);

                $meta = DB::table('meta_unidades as mu')
                ->join('clientes as c', 'mu.clientes_id', '=', 'c.id')
                ->where('mu.fecha_meta', 'like', $dateMeta->format('Y') .'-'. $dateMeta->format('m') .'%')
                ->where('c.cliente_endpoint_id', '=', $validatedData['cliente_endpoint_id'])
                ->where('mu.area_id_groot', '=', $validatedData['area_id'])
                ->whereNull('mu.deleted_at')
                ->first();

                if ($meta) {
                    $dateExisting = UnidadesDiarias::where('fecha_programacion', $validatedData['fecha_programacion'])
                    ->where('meta_unidades_id', $meta->meta_unidades_id)
                    ->first();

                    if ($dateExisting) {
                        return response()->json(['title' => 'Unidades existentes.', 'message' => 'Existen unidades programadas para el día ingresado.'], 409);
                    } else {
                        $unidades = new UnidadesDiarias();
                        $unidades->valor = $validatedData['valor'];
                        $unidades->fecha_programacion = $validatedData['fecha_programacion'];
                        $unidades->meta_unidades_id = $meta->meta_unidades_id;
                        $unidades->usuario = $validatedData['usuario'];
                        $unidades->save();
                        return response()->json(['title' => 'Guardado con exito.', 'message' => 'Unidades programadas guardadas con exito.'], 200);
                    }
                } else {
                    return response()->json(['title' => 'Meta inexistente.','message' => 'No existe una meta de unidades para el día ingresado.'], 404);
                }
            } else {
                return response()->json(['title' => 'Error al guardar.', 'message' => 'Cliente no encontrado en la BD.'], 404);
            }
        } catch (validationException $e) {
            return response()->json(['title' => 'Error de validación.', 'message' => 'Error en la validación de la unidades programadas diarias.', 'error' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return response()->json(['title' => 'Error con el servidor.', 'message' => 'Ha ocurrido un error al guardar las unidades diarias.', 'error' => $e->getMessage()], 500);
        }
    }
    
    public function list($meta_unidades_id) {
        try {
            $metaUnidades = MetaUnidades::where('meta_unidades_id', $meta_unidades_id)->first();

            if ($metaUnidades) {
                $data = UnidadesDiarias::select(
                    'unidades_diarias_id',
                    'fecha_programacion',
                    'valor',
                    'updated_at',
                    'usuario'
                )->where('meta_unidades_id', $metaUnidades->meta_unidades_id)
                ->orderBy('fecha_programacion', 'desc')
                ->get();
                return response()->json(['data' => $data], 200);
            } else {
                return response()->json(['title' => 'Meta inexistente.','message' => 'No existe la meta de unidades ingresadas.'], 404);
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
                'unidades_diarias_id' => 'required|string',
            ]);

            $unidadesDiarias = UnidadesDiarias::where('unidades_diarias_id', $validatedData['unidades_diarias_id'])->first();

            if ($unidadesDiarias) {
                $updated = UnidadesDiarias::where('unidades_diarias_id', $validatedData['unidades_diarias_id'])->update([
                    'valor' => $validatedData['valor'],
                    'usuario' => $validatedData['usuario'],
                ]);

                if ($updated) {
                    return response()->json(['title' => 'Actualización exitosa.', 'message' => 'Unidades diarias actualizada correctamente.'], 200);
                } else {
                    return response()->json(['title' => 'Actualización fallida.', 'message' => 'No se pudo actualizar las unidades diarias.'], 400);
                }
            } else {
                return response()->json(['title' => 'Error en la meta.', 'message' => 'Unidades diarias no encontradas en la BD.'], 404);
            }
        } catch (ValidationException $e) {
            return response()->json(['title' => 'Error de validación.', 'message' => 'Error en las unidades diarias ingresadas.', 'error' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return response()->json(['title' => 'Error con el servidor.', 'message' => 'Ha ocurrido un fallo con el servidor.', 'error' => $e->getMessage()], 500);
        }
    }

    public function getUnidadesDiariaID($unidades_diaria_id) {
        try {
            $unidadesDiaria = UnidadesDiarias::select('fecha_programacion','valor')
            ->where('unidades_diarias_id', $unidades_diaria_id)
            ->first();

            if ($unidadesDiaria) {
                return response()->json(['meta_unidades' => $unidadesDiaria], 200);
            } else {
                return response()->json(['title' => 'Error en la unidades diaria.', 'message' => 'Unidades diaria no encontrada en la BD.'], 404);
            }
        } catch (\Exception $e) {
            return response()->json(['title' => 'Error con el servidor.', 'message' => 'Ha ocurrido un fallo con el servidor.', 'error' => $e->getMessage()], 500);
        }
    }

    public function getUnidadesDiarias(Request $request) {
        try {
            $validateData = $request->validate([
                'fecha_programacion' => 'required|date',
                'cliente_endpoint_id' => 'required|integer',
            ]);

            $data = DB::table('unidades_diarias as ud')
            ->join('meta_unidades as mu', 'ud.meta_unidades_id', '=', 'mu.meta_unidades_id')
            ->join('clientes as c', 'mu.clientes_id', '=', 'c.id')
            ->select(
                'ud.valor as valor_diarias',
                'mu.valor as valor mensual',
            )
            ->where('ud.fecha_programacion', $validateData['fecha_programacion'])
            ->where('c.cliente_endpoint_id', $validateData['cliente_endpoint_id'])
            ->whereNull('ud.deleted_at')
            ->whereNull('mu.deleted_at')
            ->first();

            if ($data) {
                return response()->json(['title' => 'Exito.', 'data' => $data], 200);
            } else {
                return response()->json(['title' => 'No hay unidades programadas.'], 404);
            }
        } catch (\Exception $e) {
            return response()->json(['title' => 'Error con el servidor.', 'message' => 'Ha ocurrido un error al guardar las unidades diarias.', 'error' => $e->getMessage()], 500);
        }
    }
}
