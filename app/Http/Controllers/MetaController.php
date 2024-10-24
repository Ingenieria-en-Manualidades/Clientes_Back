<?php

namespace App\Http\Controllers;

use App\Models\Meta;
use App\Models\Cliente;
use Illuminate\Http\Request;
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
                'cumplimiento' => 'required|integer',
                'eficienciaProductiva' => 'required|integer',
                'calidad' => 'required|integer',
                'desperdicioME' => 'required|integer',
                'desperdicioPP' => 'required|integer',
                'cliente_endpoint_id' => 'required|integer',
            ]);

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
}
