<?php

namespace App\Http\Controllers;

use DateTime;
use App\Models\Meta;
use App\Models\Cliente;
use App\Models\Tablero_Sae;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class MetaController extends Controller
{
    public function create(Request $request)
    {
        // Log::info('Solicitud POST recibida en guardarObjetivos', $request->all());
        
        try {
            // Validar los datos entrantes
            $validatedData = $request->validate([
                'fecha' => 'required|string',
                'cumplimiento' => 'required|integer',
                'eficienciaProductiva' => 'required|integer',
                'calidad' => 'required|integer',
                'desperdicioME' => 'required|integer',
                'desperdicioPP' => 'required|integer',
                'cliente_endpoint_id' => 'required|integer',
            ]);

            // Creamos variable para poder consultar la fecha en la BD con este formato 'yyyy-mm'
            $date = new DateTime($validatedData['fecha']);

            // $tableroSae = Tablero_Sae::where('fecha', 'like', $date->format('Y') . '-' . $date->format('m') . '%')
            // ->where()->get();

            $tableroSae = DB::table('tablero_sae as ts')
            ->join('clientes as c', 'c.id', '=', 'ts.cliente_id')
            ->select('ts.*')
            ->where('ts.fecha', 'like', $date->format('Y') . '-' . $date->format('m') . '%')
            ->where('c.cliente_endpoint_id', '=', $validatedData['cliente_endpoint_id'])
            ->whereNull('ts.deleted_at')
            ->whereNull('c.deleted_at')
            ->get();

            if ($tableroSae->isEmpty()) {
                $clienteID = Cliente::select('clientes.id')
                ->where('clientes.cliente_endpoint_id', '=', $validatedData['cliente_endpoint_id'])
                ->get();
    
                if ($clienteID->isEmpty()) {
                    return response()->json([
                        'message' => 'Cliente no encontrado en la base de datos.',
                        'errors' => $request
                    ], 404);
                }else {
                    // Guardar los datos en la base de datos
                    $meta = new Meta();
                    $meta->cumplimiento = $validatedData['cumplimiento'];
                    $meta->eficiencia_productiva = $validatedData['eficienciaProductiva'];
                    $meta->calidad = $validatedData['calidad'];
                    $meta->desperdicio_me = $validatedData['desperdicioME'];
                    $meta->desperdicio_pp = $validatedData['desperdicioPP'];
                    $meta->save();
                    // Devolver una respuesta exitosa
                    return response()->json(['success' => true,'message' => 'Meta creado con éxito', 'data' => $request, 'meta_id' => $meta->meta_id, 'cliente_id' => $clienteID[0]->id], 200);
                }
            } else {
                return response()->json(['success' => false, 'message' => 'Ya existe una meta con esta fecha.'], 406);
            }

        } catch (ValidationException $e) {
            // Si la validación falla, se capturan los errores y se devuelven
            return response()->json([
                'message' => 'Error en la validación de los datos de Meta',
                'errors' => $request
            ], 422);
        } catch (\Exception $e) {
            // Si ocurre cualquier otro error, devolver un error general
            return response()->json([
                'message' => 'Ha ocurrido un error al guardar las metas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function list(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'cliente_endpoint_id' => 'required|integer',
                'fecha_inicio' => 'nullable|date',
                'fecha_fin' => 'nullable|date',
            ]);

            $dateEnd = isset($validatedData['fecha_fin'])
                ? Carbon::parse($validatedData['fecha_fin'])->endOfDay()
                : Carbon::today()->endOfDay();
            $dateStart = isset($validatedData['fecha_inicio'])
                ? Carbon::parse($validatedData['fecha_inicio'])->startOfDay()
                : Carbon::today()->subYear()->startOfDay();

            $metas = DB::table('tablero_sae as ts')
            ->join('meta as m', 'm.meta_id', '=', 'ts.meta_id')
            ->join('clientes as c', 'c.id', '=', 'ts.cliente_id')
            ->select(
                'ts.tablero_sae_id',
                'm.meta_id',
                'ts.fecha',
                'm.cumplimiento',
                'm.eficiencia_productiva',
                'm.calidad',
                'm.desperdicio_me',
                'm.desperdicio_pp',
                'm.created_at',
                'm.updated_at'
            )
            ->where('c.cliente_endpoint_id', '=', $validatedData['cliente_endpoint_id'])
            ->whereBetween('ts.fecha', [$dateStart, $dateEnd])
            ->whereNull('ts.deleted_at')
            ->whereNull('m.deleted_at')
            ->whereNull('c.deleted_at')
            ->orderBy('ts.fecha', 'desc')
            ->get();

            return response()->json(['data' => $metas], 200);
        } catch (ValidationException $e) {
            return response()->json(['title' => 'Error de validación.', 'message' => 'Error en los filtros de metas.', 'error' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return response()->json(['title' => 'Error con el servidor.', 'message' => 'Ha ocurrido un fallo al listar las metas.', 'error' => $e->getMessage()], 500);
        }
    }
}
