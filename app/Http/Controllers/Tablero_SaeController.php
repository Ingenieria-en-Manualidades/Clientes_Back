<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Tablero_Sae;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class Tablero_SaeController extends Controller
{
    public function create(Request $request){
        try {
            // Validar los datos entrantes
            $validatedData = $request->validate([
                'fecha' => 'required|string',
                'meta_id' => 'required|integer',
                'cliente_id' => 'required|integer',
            ]);

            // Guardar los datos en la base de datos
            $tablero = new Tablero_Sae();
            $tablero->fecha = $validatedData['fecha'];
            $tablero->meta_id = $validatedData['meta_id'];
            $tablero->cliente_id = $validatedData['cliente_id'];
            $tablero->save();

            // Devolver una respuesta exitosa en caso de no fallar
            return response()->json(['success' => true,'message' => 'Tablero sae creado con éxito.', 'data' => $request], 200);
        } catch (ValidationException $e) {
            // Si la validación falla, se capturan los errores y se devuelven
            return response()->json([
                'message' => 'Error en la validación de los datos del Tablero sae.',
                'errors' => $request
            ], 422);
        } catch (\Exception $e) {
            // Si ocurre cualquier otro error, devolver un error general
            return response()->json([
                'message' => 'Ha ocurrido un error al guardar el Tablero sae.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
