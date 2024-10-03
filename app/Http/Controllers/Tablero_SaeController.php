<?php

namespace App\Http\Controllers;

use App\Models\Tablero_Sae;
use Illuminate\Http\Request;

class Tablero_SaeController extends Controller
{
    public function guardarTablero(Request $request){
        try {
            // Validar los datos entrantes
            $validatedData = $request->validate([
                'mes' => 'required|string',
            ]);

            // Guardar los datos en la base de datos
            $tablero = new Tablero_Sae();
            $tablero->mes = $validatedData['mes'];
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
