<?php

namespace App\Http\Controllers;
use DateTime;
use App\Models\Cliente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FileController extends Controller
{
    public function saveFileCalidad(Request $request){
        try {
            // Validar los datos entrantes
            $validatedData = $request->validate([
                'archivo' => 'required|file|mimes:pdf',
                'cliente_endpoint_id' => 'required|integer',
            ]);

            $nombreCliente = Cliente::select('clientes.nombre')->where('clientes.cliente_endpoint_id', '=', $validatedData['cliente_endpoint_id'])
            ->get();

            if ($nombreCliente->isEmpty()) {
                return response()->json(['message' => 'No existe un cliente con ese ID.','errors' => $request], 404);
            } else {
                $fecha = new DateTime();
                $directorioCliente = str_replace(' ','-', $nombreCliente[0]->nombre);
            
                if (!Storage::disk('evidencias')->exists('Calidad/' . $directorioCliente)) {
                    Storage::disk('evidencias')->makeDirectory('Calidad/' . $directorioCliente);
                }

                $directorio = 'Calidad/' . $directorioCliente . '/' . $fecha->format('Y');

                if (!Storage::disk('evidencias')->exists($directorio)) {
                    Storage::disk('evidencias')->makeDirectory($directorio);
                }
                
                $path = $request->file('archivo')->storeAs($directorio, 'archivoEjemplo.pdf', 'evidencias');
            }
            
            return response()->json([
                'message' => 'URL TOMALO O DEJALO',
                'path' => "no jodas"
            ], 200);
        } catch (ValidationException $e) {
            // Si la validación falla, se capturan los errores y se devuelven
            return response()->json([
                'message' => 'Error en la validación de los datos de calidad',
                'errors' => $request
            ], 422);
        } catch (\Exception $e) {
            // Si ocurre cualquier otro error, devolver un error general
            return response()->json([
                'message' => 'Ha ocurrido un error al guardar los objetivos',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function createFile(){
        Storage::disk('evidencias')->put("soyArchivo", "Hola :-)");

        $url = Storage::disk('evidencias')->url('soyArchivo');

        $file = Storage::disk('evidencias')->download('soyArchivo');

        Storage::disk('evidencias')->makeDirectory('Calidad/PostobonYumbo/2024');
        
        // $path = $file->storeAs('Calidad', 'archivoDescargado.txt', 'evidencias');

        return response()->json([
            'message' => 'URL TOMALO O DEJALO',
            'archivo' => $url
        ], 200);
    }
}
