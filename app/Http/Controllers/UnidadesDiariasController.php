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
                'usuario' => 'required|string',
            ]);
            $clienteID = Cliente::where('cliente_endpoint_id', $validatedData['cliente_endpoint_id'])->first();
            if ($clienteID) {
                $dateMeta = new DateTime($validatedData['fecha_programacion']);

                $meta = DB::table('meta_unidades as mu')
                ->join('clientes as c', 'mu.clientes_id', '=', 'c.id')
                ->where('mu.fecha_meta', 'like', $dateMeta->format('Y') .'-'. $dateMeta->format('m') .'%')
                ->where('c.cliente_endpoint_id', '=', $validatedData['cliente_endpoint_id'])
                ->whereNull('mu.deleted_at')
                ->first();

                if ($meta) {
                    $dateExisting = UnidadesDiarias::where('fecha_programacion', $validatedData['fecha_programacion'])
                    ->where('meta_unidades_id', $meta->meta_unidades_id)
                    ->first();

                    if ($dateExisting) {
                        return response()->json(['title' => 'Unidades existentes.', 'message' => 'Ya hay unidades programadas para el día ingresado.'], 409);
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
